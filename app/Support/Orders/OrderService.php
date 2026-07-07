<?php

namespace App\Support\Orders;

use App\Enums\AssignmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceReturnStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\LedgerEntryType;
use App\Enums\OfferSource;
use App\Enums\OrderStatus;
use App\Enums\PharmacyStatus;
use App\Enums\ProductStatus;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\RepPharmacyAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /** @param  array<string, mixed>  $data */
    public function submit(User $rep, array $data): Order
    {
        $pharmacy = Pharmacy::query()->findOrFail($data['pharmacy_id']);

        $this->assertRepAssignedToPharmacy($rep, $pharmacy);
        $this->assertPharmacyActive($pharmacy);

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages(['items' => ['Order must contain at least one item.']]);
        }

        return DB::transaction(function () use ($rep, $data, $pharmacy): Order {
            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'rep_id' => $rep->id,
                'pharmacy_id' => $pharmacy->id,
                'region_id' => $pharmacy->region_id,
                'sub_region_id' => $pharmacy->sub_region_id,
                'status' => OrderStatus::PendingReview,
                'rep_notes' => $data['rep_notes'] ?? null,
                'submitted_at' => now(),
            ]);

            $this->syncItems($order, $data['items'], isRepSubmit: true);
            $this->recalculateTotals($order);

            return $order->refresh()->load(['items.product', 'items.company', 'pharmacy', 'region', 'subRegion', 'rep']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function modify(Order $order, array $data, User $actor): Order
    {
        $this->assertInvoicerCanEdit($order);

        return DB::transaction(function () use ($order, $data, $actor): Order {
            if ($order->original_snapshot === null) {
                $order->update([
                    'original_snapshot' => $this->snapshotOrder($order),
                ]);
            }

            if (array_key_exists('invoicer_notes', $data)) {
                $order->invoicer_notes = $data['invoicer_notes'];
            }

            if (isset($data['items'])) {
                if ($order->status === OrderStatus::PartiallyFulfilled) {
                    $this->syncUninvoicedItems($order, $data['items']);
                } else {
                    $this->syncItems($order, $data['items'], isRepSubmit: false);
                }
            }

            $order->status = OrderStatus::Modified;
            $order->save();

            $this->recalculateTotals($order);

            return $order->refresh()->load(['items.product', 'items.company', 'pharmacy', 'region', 'subRegion', 'rep']);
        });
    }

    public function reject(Order $order, string $reason, User $actor): Order
    {
        if (! in_array($order->status, OrderStatus::rejectable(), true)) {
            throw ValidationException::withMessages(['status' => ['Order cannot be rejected in its current state.']]);
        }

        if ($order->hasInvoicedShipments()) {
            throw ValidationException::withMessages(['status' => ['Order with invoiced shipments cannot be rejected.']]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Rejection reason is required.']]);
        }

        $order->update([
            'status' => OrderStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        return $order->refresh();
    }

    public function cancelByRep(Order $order, User $rep): Order
    {
        if ($order->rep_id !== $rep->id) {
            abort(403);
        }

        if ($order->status !== OrderStatus::PendingReview) {
            throw ValidationException::withMessages(['status' => ['Only pending orders can be cancelled by rep.']]);
        }

        if ($order->hasInvoicedShipments()) {
            throw ValidationException::withMessages(['status' => ['Order already has invoices.']]);
        }

        $order->update(['status' => OrderStatus::CancelledByRep]);

        return $order->refresh();
    }

    public function cancelByInvoicer(Order $order, string $reason, User $actor): Order
    {
        if (! in_array($order->status, OrderStatus::cancellableByInvoicer(), true)) {
            throw ValidationException::withMessages(['status' => ['Order cannot be cancelled in its current state.']]);
        }

        if ($order->hasInvoicedShipments()) {
            throw ValidationException::withMessages(['status' => ['Order with invoiced shipments cannot be cancelled.']]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Cancellation reason is required.']]);
        }

        $order->update([
            'status' => OrderStatus::CancelledByInvoicer,
            'cancellation_reason' => $reason,
        ]);

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{invoice: Invoice, order: Order}
     */
    public function approveShipment(Order $order, array $data, User $actor): array
    {
        if (! in_array($order->status, OrderStatus::editableByInvoicer(), true)) {
            throw ValidationException::withMessages(['status' => ['Order cannot be approved in its current state.']]);
        }

        $pharmacy = $order->pharmacy()->firstOrFail();
        $this->assertPharmacyActive($pharmacy);

        return DB::transaction(function () use ($order, $data, $actor, $pharmacy): array {
            $order->load('items.product');

            $lines = $this->resolveShipmentLines($order, $data['lines'] ?? null);
            $shipmentNumber = $this->nextShipmentNumber($order);

            foreach ($lines as $line) {
                /** @var OrderItem $item */
                $item = $line['item'];
                $qty = $line['quantity'];
                $bonusQty = $line['bonus_qty'];
                $product = $item->product;

                if ($product === null || $product->status !== ProductStatus::Active) {
                    throw ValidationException::withMessages([
                        'lines' => ["Product {$item->product_name} is not available."],
                    ]);
                }

                $deductTotal = $qty + $bonusQty;

                if ($product->quantity < $deductTotal) {
                    throw ValidationException::withMessages([
                        'lines' => ["Insufficient stock for {$item->product_name}."],
                    ]);
                }
            }

            $subtotal = '0';
            $discountTotal = '0';
            $total = '0';

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'order_id' => $order->id,
                'shipment_number' => $shipmentNumber,
                'invoice_type' => InvoiceType::FromOrder,
                'rep_id' => $order->rep_id,
                'pharmacy_id' => $order->pharmacy_id,
                'region_id' => $order->region_id,
                'status' => InvoiceStatus::Approved,
                'return_status' => InvoiceReturnStatus::None,
                'balance_before' => $pharmacy->current_balance,
                'approved_at' => now(),
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                /** @var OrderItem $item */
                $item = $line['item'];
                $qty = $line['quantity'];
                $bonusQty = $line['bonus_qty'];
                $product = $item->product;
                $companyName = $item->company?->name ?? '';

                $lineSubtotal = bcmul((string) $qty, (string) $item->unit_price, 2);
                $lineDiscount = bcmul(
                    bcdiv((string) $item->discount, (string) max($item->quantity, 1), 4),
                    (string) $qty,
                    2,
                );
                $lineTotal = bcsub($lineSubtotal, $lineDiscount, 2);

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'company_name' => $companyName,
                    'quantity' => $qty,
                    'bonus_qty' => $bonusQty,
                    'unit_price' => $item->unit_price,
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'offer_source' => $item->offer_source,
                    'promo_snapshot' => $item->promo_snapshot,
                ]);

                $item->increment('quantity_invoiced', $qty);
                $item->increment('bonus_qty_invoiced', $bonusQty);

                $product->decrement('quantity', $qty + $bonusQty);

                InventoryMovement::query()->create([
                    'product_id' => $product->id,
                    'type' => InventoryMovementType::Sale,
                    'quantity_out' => $qty,
                    'bonus_qty' => $bonusQty,
                    'reference_type' => 'invoice',
                    'reference_id' => $invoice->id,
                    'created_by' => $actor->id,
                    'occurred_at' => now(),
                ]);

                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $discountTotal = bcadd($discountTotal, $lineDiscount, 2);
                $total = bcadd($total, $lineTotal, 2);
            }

            $balanceAfter = bcadd((string) $pharmacy->current_balance, $total, 2);

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'balance_after' => $balanceAfter,
            ]);

            LedgerEntry::query()->create([
                'pharmacy_id' => $pharmacy->id,
                'type' => LedgerEntryType::InvoiceDebit,
                'amount' => $total,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'description' => "Invoice {$invoice->invoice_number} from order {$order->order_number}",
                'created_by' => $actor->id,
                'occurred_at' => now(),
            ]);

            $pharmacy->update(['current_balance' => $balanceAfter]);

            $order->refresh()->load('items');
            $allInvoiced = $order->items->every(fn (OrderItem $item) => $item->isFullyInvoiced());

            $order->update([
                'status' => $allInvoiced ? OrderStatus::Approved : OrderStatus::PartiallyFulfilled,
            ]);

            return [
                'invoice' => $invoice->refresh()->load('items'),
                'order' => $order->refresh()->load(['items', 'pharmacy', 'region', 'rep']),
            ];
        });
    }

    /** @return array<string, int> */
    public function dashboardStats(): array
    {
        $base = Order::query();

        return [
            'pending_review' => (clone $base)->where('status', OrderStatus::PendingReview)->count(),
            'modified' => (clone $base)->where('status', OrderStatus::Modified)->count(),
            'partially_fulfilled' => (clone $base)->where('status', OrderStatus::PartiallyFulfilled)->count(),
            'approved' => (clone $base)->where('status', OrderStatus::Approved)->count(),
            'rejected' => (clone $base)->where('status', OrderStatus::Rejected)->count(),
            'cancelled' => (clone $base)->whereIn('status', [
                OrderStatus::CancelledByRep,
                OrderStatus::CancelledByInvoicer,
            ])->count(),
            'total' => (clone $base)->count(),
        ];
    }

    private function assertRepAssignedToPharmacy(User $rep, Pharmacy $pharmacy): void
    {
        $assigned = RepPharmacyAssignment::query()
            ->where('rep_id', $rep->id)
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'pharmacy_id' => ['Rep is not assigned to this pharmacy.'],
            ]);
        }
    }

    private function assertPharmacyActive(Pharmacy $pharmacy): void
    {
        if ($pharmacy->status !== PharmacyStatus::Active) {
            throw ValidationException::withMessages([
                'pharmacy_id' => ['Pharmacy is not active.'],
            ]);
        }
    }

    private function assertInvoicerCanEdit(Order $order): void
    {
        if (! in_array($order->status, OrderStatus::editableByInvoicer(), true)) {
            throw ValidationException::withMessages(['status' => ['Order cannot be modified in its current state.']]);
        }
    }

    /** @param  list<array<string, mixed>>  $items */
    private function syncUninvoicedItems(Order $order, array $items): void
    {
        $order->items()->where('quantity_invoiced', 0)->delete();

        foreach ($items as $row) {
            if (isset($row['order_item_id'])) {
                $existing = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->where('id', $row['order_item_id'])
                    ->first();

                if ($existing !== null && $existing->quantity_invoiced > 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Invoiced lines cannot be modified.'],
                    ]);
                }
            }

            $this->createItemFromRow($order, $row, isRepSubmit: false);
        }
    }

    /** @param  list<array<string, mixed>>  $items */
    private function syncItems(Order $order, array $items, bool $isRepSubmit): void
    {
        $order->items()->delete();

        foreach ($items as $row) {
            $this->createItemFromRow($order, $row, $isRepSubmit);
        }
    }

    /** @param  array<string, mixed>  $row */
    private function createItemFromRow(Order $order, array $row, bool $isRepSubmit): void
    {
        $product = Product::query()->with(['company', 'baseOffer'])->findOrFail($row['product_id']);

        if ($isRepSubmit) {
            if (! $product->rep_visible) {
                throw ValidationException::withMessages([
                    'items' => ["Product {$product->name} is not visible to reps."],
                ]);
            }

            if ($product->status !== ProductStatus::Active || $product->isExpired()) {
                throw ValidationException::withMessages([
                    'items' => ["Product {$product->name} is not available."],
                ]);
            }
        }

        $quantity = (int) $row['quantity'];
        $unitPrice = (string) ($row['unit_price'] ?? $product->price);
        $discount = (string) ($row['discount'] ?? 0);
        $offerSource = OfferSource::from($row['offer_source'] ?? OfferSource::NoOffer->value);
        $bonusQty = (int) ($row['bonus_qty'] ?? 0);

        if ($bonusQty === 0 && $offerSource === OfferSource::BaseProductOffer && $product->baseOffer !== null) {
            $bonusQty = $this->calculateBonusQty(
                $quantity,
                $product->baseOffer->required_qty,
                $product->baseOffer->bonus_qty,
            );
        }

        $lineSubtotal = bcmul((string) $quantity, $unitPrice, 2);
        $lineTotal = bcsub($lineSubtotal, $discount, 2);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'product_name' => $product->name,
            'scientific_name' => $product->scientific_name,
            'quantity' => $quantity,
            'bonus_qty' => $bonusQty,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'line_total' => $lineTotal,
            'offer_source' => $offerSource,
            'promo_snapshot' => $row['promo_snapshot'] ?? ($product->baseOffer ? [
                'required_qty' => $product->baseOffer->required_qty,
                'bonus_qty' => $product->baseOffer->bonus_qty,
            ] : null),
            'expiry_date' => $product->expiry_date,
        ]);
    }

    private function recalculateTotals(Order $order): void
    {
        $order->load('items');

        $subtotal = '0';
        $discountTotal = '0';
        $total = '0';

        foreach ($order->items as $item) {
            $lineSubtotal = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $discountTotal = bcadd($discountTotal, (string) $item->discount, 2);
            $total = bcadd($total, (string) $item->line_total, 2);
        }

        $order->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'total' => $total,
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshotOrder(Order $order): array
    {
        $order->load('items');

        return [
            'status' => $order->status->value,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'total' => $order->total,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'bonus_qty' => $item->bonus_qty,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'line_total' => $item->line_total,
                'offer_source' => $item->offer_source->value,
            ])->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $linesInput
     * @return list<array{item: OrderItem, quantity: int, bonus_qty: int}>
     */
    private function resolveShipmentLines(Order $order, ?array $linesInput): array
    {
        if ($linesInput === null) {
            $resolved = [];

            foreach ($order->items as $item) {
                $remaining = $item->remainingQuantity();

                if ($remaining <= 0) {
                    continue;
                }

                $resolved[] = [
                    'item' => $item,
                    'quantity' => $remaining,
                    'bonus_qty' => $item->remainingBonusQuantity(),
                ];
            }

            if ($resolved === []) {
                throw ValidationException::withMessages(['lines' => ['No remaining items to invoice.']]);
            }

            return $resolved;
        }

        $resolved = [];

        foreach ($linesInput as $row) {
            $item = $order->items->firstWhere('id', $row['order_item_id']);

            if ($item === null) {
                throw ValidationException::withMessages(['lines' => ['Invalid order item.']]);
            }

            $qty = (int) ($row['quantity'] ?? $item->remainingQuantity());
            $bonusQty = (int) ($row['bonus_qty'] ?? 0);

            if ($qty > $item->remainingQuantity()) {
                throw ValidationException::withMessages([
                    'lines' => ["Quantity exceeds remaining for {$item->product_name}."],
                ]);
            }

            if ($qty <= 0) {
                continue;
            }

            $resolved[] = [
                'item' => $item,
                'quantity' => $qty,
                'bonus_qty' => $bonusQty,
            ];
        }

        if ($resolved === []) {
            throw ValidationException::withMessages(['lines' => ['At least one line is required.']]);
        }

        return $resolved;
    }

    private function nextShipmentNumber(Order $order): int
    {
        $max = Invoice::query()->where('order_id', $order->id)->max('shipment_number');

        return ((int) $max) + 1;
    }

    private function calculateBonusQty(int $quantity, int $requiredQty, int $bonusPerBlock): int
    {
        if ($requiredQty <= 0) {
            return 0;
        }

        return intdiv($quantity, $requiredQty) * $bonusPerBlock;
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd');
        $count = Order::query()->where('order_number', 'like', "{$prefix}-%")->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $count = Invoice::query()->where('invoice_number', 'like', "{$prefix}-%")->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
