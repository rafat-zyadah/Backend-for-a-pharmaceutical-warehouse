<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->foreignUuid('rep_id')->constrained('users');
            $table->foreignUuid('pharmacy_id')->constrained('pharmacies');
            $table->foreignUuid('region_id')->constrained('regions');
            $table->foreignUuid('sub_region_id')->constrained('sub_regions');
            $table->string('status', 32)->default('pending_review');
            $table->text('rep_notes')->nullable();
            $table->text('invoicer_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->json('original_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'submitted_at']);
            $table->index(['rep_id', 'status']);
            $table->index(['pharmacy_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('company_id')->constrained('companies');
            $table->string('product_name');
            $table->string('scientific_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_invoiced')->default(0);
            $table->unsignedInteger('bonus_qty')->default(0);
            $table->unsignedInteger('bonus_qty_invoiced')->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->string('offer_source', 32)->default('no_offer');
            $table->json('promo_snapshot')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedSmallInteger('shipment_number')->default(1);
            $table->string('invoice_type', 32);
            $table->foreignUuid('rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();
            $table->foreignUuid('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('status', 32)->default('approved');
            $table->string('return_status', 32)->default('none');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('balance_before', 14, 2)->nullable();
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->timestamp('pdf_extracted_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['order_id', 'shipment_number']);
            $table->index(['status', 'approved_at']);
            $table->index('pharmacy_id');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('product_name');
            $table->string('company_name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('bonus_qty')->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->string('offer_source', 32)->default('no_offer');
            $table->json('promo_snapshot')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pharmacy_id')->constrained('pharmacies');
            $table->string('type', 32);
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 64);
            $table->uuid('reference_id');
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['pharmacy_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('type', 32);
            $table->unsignedInteger('quantity_in')->default(0);
            $table->unsignedInteger('quantity_out')->default(0);
            $table->unsignedInteger('bonus_qty')->default(0);
            $table->string('reference_type', 64);
            $table->uuid('reference_id');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
