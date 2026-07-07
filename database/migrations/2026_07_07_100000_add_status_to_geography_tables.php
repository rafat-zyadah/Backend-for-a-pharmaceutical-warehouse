<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('name');
            $table->unique('name');
        });

        Schema::table('sub_regions', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('sub_regions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn('status');
        });
    }
};
