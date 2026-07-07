<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('scientific_name')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->date('purchase_date');
            $table->date('production_date');
            $table->date('expiry_date');
            $table->boolean('rep_visible')->default(true);
            $table->string('status', 32)->default('active');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name', 'expiry_date']);
            $table->index(['company_id', 'status']);
            $table->index('expiry_date');
        });

        Schema::create('product_base_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('required_qty');
            $table->unsignedInteger('bonus_qty');
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_base_offers');
        Schema::dropIfExists('products');
    }
};
