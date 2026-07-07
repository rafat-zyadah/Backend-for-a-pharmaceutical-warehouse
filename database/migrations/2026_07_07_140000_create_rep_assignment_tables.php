<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rep_region_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rep_id')->constrained('users');
            $table->foreignUuid('region_id')->constrained('regions');
            $table->string('status', 32)->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rep_id', 'status']);
            $table->index(['region_id', 'status']);
        });

        Schema::create('rep_pharmacy_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rep_id')->constrained('users');
            $table->foreignUuid('pharmacy_id')->constrained('pharmacies');
            $table->string('status', 32)->default('active');
            $table->boolean('is_primary')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rep_id', 'status']);
            $table->index(['pharmacy_id', 'status']);
            $table->index(['pharmacy_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_pharmacy_assignments');
        Schema::dropIfExists('rep_region_assignments');
    }
};
