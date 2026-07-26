<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->decimal('commission_pct', 8, 4)->default(5.0000);
            $table->decimal('commission_fixed', 18, 8)->default(0);
            $table->decimal('min_order_amount', 18, 8)->default(1);
            $table->decimal('max_order_amount', 18, 8)->nullable();
            $table->boolean('auto_release')->default(true);
            $table->unsignedInteger('payout_hold_minutes')->default(0);
            $table->foreignId('commission_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('marketplace_settings')->insert([
            'enabled'             => false,
            'commission_pct'      => 5.0000,
            'commission_fixed'    => 0,
            'min_order_amount'    => 1,
            'max_order_amount'    => null,
            'auto_release'        => true,
            'payout_hold_minutes' => 0,
            'commission_user_id'  => null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_settings');
    }
};
