<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('responsible')->nullable();
            $table->string('phone');
            $table->string('phone_secondary')->nullable();
            $table->foreignUuid('region_id')->constrained('regions');
            $table->foreignUuid('sub_region_id')->constrained('sub_regions');
            $table->string('address')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status', 32)->default('active');
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'sub_region_id']);
            $table->index('status');
            $table->index('phone');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
