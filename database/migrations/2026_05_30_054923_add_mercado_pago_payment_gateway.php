<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payment_gateways')->updateOrInsert(
            ['code' => 'mercadopago'],
            [
                'logo' => 'general/static/default/mercado_pago.png',
                'name' => 'Mercado Pago',
                'currencies' => json_encode(['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU']),
                'credentials' => json_encode([
                    'access_token' => 'access_token',
                    'webhook_secret' => 'webhook_secret',
                    'sandbox' => true,
                ]),
                'withdraw_field' => null,
                'ipn' => true,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Cache::forget('payment_gateways_all');
        Cache::forget('payment_gateway_code_mercadopago');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payment_gateways')->where('code', 'mercadopago')->delete();

        Cache::forget('payment_gateways_all');
        Cache::forget('payment_gateway_code_mercadopago');
    }
};
