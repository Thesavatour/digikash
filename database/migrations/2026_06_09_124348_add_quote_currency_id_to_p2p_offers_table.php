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
        Schema::table('p2p_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_currency_id')->nullable()->after('wallet_id');

            $table->index('quote_currency_id');
            $table->foreign('quote_currency_id')->references('id')->on('currencies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_offers', function (Blueprint $table) {
            $table->dropForeign(['quote_currency_id']);
            $table->dropIndex(['quote_currency_id']);
            $table->dropColumn('quote_currency_id');
        });
    }
};
