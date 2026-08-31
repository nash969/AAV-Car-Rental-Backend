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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_number', 100)->nullable()->change();
            $table->string('proof_path')->nullable()->change();
            $table->dateTime('customer_confirmed_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_number', 100)->nullable(false)->change();
            $table->string('proof_path')->nullable(false)->change();
            $table->dateTime('customer_confirmed_at')->nullable(false)->change();
        });
    }
};
