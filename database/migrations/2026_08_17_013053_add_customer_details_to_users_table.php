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
        Schema::table('users', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->string('driver_license_number')->nullable();
            $table->string('government_id_path')->nullable();
            $table->string('driver_license_path')->nullable();
            $table->string('selfie_id_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'driver_license_number',
                'government_id_path',
                'driver_license_path',
                'selfie_id_path',
            ]);
        });
    }
};
