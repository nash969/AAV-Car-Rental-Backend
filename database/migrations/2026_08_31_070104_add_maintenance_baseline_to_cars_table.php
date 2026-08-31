<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedInteger('maintenance_baseline_mileage')
                ->nullable()
                ->after('current_mileage');

            $table->date('maintenance_baseline_date')
                ->nullable()
                ->after('maintenance_baseline_mileage');

            $table->date('last_inspection_date')
                ->nullable()
                ->after('maintenance_baseline_date');

            $table->boolean('maintenance_initialized')
                ->default(false)
                ->after('last_inspection_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn([
                'maintenance_baseline_mileage',
                'maintenance_baseline_date',
                'last_inspection_date',
                'maintenance_initialized',
            ]);
        });
    }
};
