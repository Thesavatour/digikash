<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_id', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corridor_id')->constrained('remittance_corridors')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_currency', 3);
            $table->string('destination_currency', 3);
            $table->decimal('send_amount', 15, 2)->comment('Amount the sender pays excluding fee');
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('total_pay', 15, 2)->comment('send_amount + fee_amount');
            $table->decimal('exchange_rate', 18, 8)->comment('Locked rate at quote time');
            $table->decimal('mid_market_rate', 18, 8)->nullable();
            $table->decimal('receive_amount', 15, 2)->comment('Amount beneficiary receives');
            $table->string('payout_method', 32);
            $table->timestamp('expires_at');
            $table->boolean('is_consumed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_consumed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_quotes');
    }
};
