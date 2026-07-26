<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_corridors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_country', 2);
            $table->string('source_currency', 3);
            $table->string('destination_country', 2);
            $table->string('destination_currency', 3);
            $table->json('allowed_payout_methods');
            $table->string('payout_gateway', 64)->comment('Driver code: bitnob, flutterwave, manual, etc.');
            $table->decimal('fx_spread_percent', 8, 4)->default(0)->comment('Margin added on top of mid-market rate');
            $table->decimal('fixed_fee', 15, 2)->default(0);
            $table->decimal('percent_fee', 8, 4)->default(0);
            $table->decimal('min_amount', 15, 2)->default(1);
            $table->decimal('max_amount', 15, 2)->default(10000);
            $table->unsignedSmallInteger('quote_ttl_minutes')->default(15);
            $table->unsignedTinyInteger('required_kyc_tier')->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['source_country', 'source_currency', 'destination_country', 'destination_currency'], 'remittance_corridors_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_corridors');
    }
};
