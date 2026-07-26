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
        Schema::table('deposit_methods', function (Blueprint $table): void {
            $table->integer('payment_gateway_id')->nullable()->comment('Payment gateway id')->change();
        });

        Schema::table('withdraw_methods', function (Blueprint $table): void {
            $table->integer('payment_gateway_id')->nullable()->comment('Payment gateway id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_methods', function (Blueprint $table): void {
            $table->integer('payment_gateway_id')->nullable(false)->comment('Payment gateway id')->change();
        });

        Schema::table('withdraw_methods', function (Blueprint $table): void {
            $table->integer('payment_gateway_id')->nullable(false)->comment('Payment gateway id')->change();
        });
    }
};
