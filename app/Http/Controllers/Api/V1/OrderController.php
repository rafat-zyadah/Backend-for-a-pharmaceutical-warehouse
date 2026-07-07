<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApproveOrderShipmentRequest;
use App\Http\Requests\Api\V1\CancelOrderRequest;
use App\Http\Requests\Api\V1\RejectOrderRequest;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Support\Audit\StateTransitionLogger;
use App\Support\Orders\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'summary' => $this->orderService->dashboardStats(),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->with(['rep', 'pharmacy', 'region', 'subRegion'])
            ->withCount('items')
            ->orderByDesc('submitted_at');

        if ($request->user()->role === UserRole::Rep) {
            $query->where('rep_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('rep_id') && $request->user()->can('orders.manage')) {
            $query->where('rep_id', $request->string('rep_id')->toString());
        }

        if ($request->filled('pharmacy_id')) {
            $query->where('pharmacy_id', $request->string('pharmacy_id')->toString());
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->string('region_id')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('order_number', 'like', "%{$search}%");
        }

        if ($request->filled('from_date')) {
            $query->whereDate('submitted_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('submitted_at', '<=', $request->string('to_date')->toString());
        }

        return OrderResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->submit($request->user(), $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'order',
            entityId: $order->id,
            event: 'submit',
            fromState: null,
            toState: OrderStatus::PendingReview->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Order submitted.',
            'order' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        $order->load([
            'rep',
            'pharmacy.region',
            'pharmacy.subRegion',
            'region',
            'subRegion',
            'items.product',
            'items.company',
            'invoices',
        ]);

        return response()->json(new OrderResource($order));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $previousStatus = $order->status->value;

        $order = $this->orderService->modify($order, $request->validated(), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'order',
            entityId: $order->id,
            event: 'invoicer_modify',
            fromState: $previousStatus,
            toState: $order->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['original_snapshot' => $order->original_snapshot],
        );

        return response()->json([
            'message' => 'Order updated.',
            'order' => new OrderResource($order),
        ]);
    }

    public function approveShipment(ApproveOrderShipmentRequest $request, Order $order): JsonResponse
    {
        $previousStatus = $order->status->value;

        $result = $this->orderService->approveShipment($order, $request->validated(), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'order',
            entityId: $result['order']->id,
            event: 'approve_shipment',
            fromState: $previousStatus,
            toState: $result['order']->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['invoice_id' => $result['invoice']->id],
        );

        return response()->json([
            'message' => 'Shipment approved and invoice created.',
            'order' => new OrderResource($result['order']),
            'invoice' => new InvoiceResource($result['invoice']),
        ]);
    }

    public function reject(RejectOrderRequest $request, Order $order): JsonResponse
    {
        $previousStatus = $order->status->value;

        $order = $this->orderService->reject($order, $request->input('reason'), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'order',
            entityId: $order->id,
            event: 'reject',
            fromState: $previousStatus,
            toState: OrderStatus::Rejected->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            reason: $request->input('reason'),
        );

        return response()->json([
            'message' => 'Order rejected.',
            'order' => new OrderResource($order),
        ]);
    }

    public function cancelByInvoicer(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $previousStatus = $order->status->value;

        $order = $this->orderService->cancelByInvoicer($order, $request->input('reason'), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'order',
            entityId: $order->id,
            event: 'cancel_by_invoicer',
            fromState: $previousStatus,
            toState: OrderStatus::CancelledByInvoicer->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            reason: $request->input('reason'),
        );

        return response()->json([
            'message' => 'Order cancelled.',
            'order' => new OrderResource($order),
        ]);
    }

    public function cancelByRep(Request $request, Order $order): JsonResponse
    {
        if (! $request->user()->can('orders.submit')) {
            abort(403);
        }

        $previousStatus = $order->status->value;

        $order = $this->orderService->cancelByRep($order, $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'order',
            entityId: $order->id,
            event: 'cancel_by_rep',
            fromState: $previousStatus,
            toState: OrderStatus::CancelledByRep->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Order cancelled.',
            'order' => new OrderResource($order),
        ]);
    }

    private function authorizeOrderAccess(Request $request, Order $order): void
    {
        if ($request->user()->role === UserRole::Rep && $order->rep_id !== $request->user()->id) {
            abort(403);
        }

        if ($request->user()->role === UserRole::Rep && ! $request->user()->can('orders.submit')) {
            abort(403);
        }

        if ($request->user()->role !== UserRole::Rep && ! $request->user()->can('orders.view')) {
            abort(403);
        }
    }
}
