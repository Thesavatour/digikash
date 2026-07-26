<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique()->comment('Public-facing transfer reference (RMTxxxxx)');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('remittance_quotes')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('corridor_id')->constrained('remittance_corridors')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('source_currency', 3);
            $table->string('destination_currency', 3);
            $table->decimal('send_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2);
            $table->decimal('exchange_rate', 18, 8);
            $table->decimal('receive_amount', 15, 2);
            $table->string('payout_method', 32);
            $table->string('payout_gateway', 64);
            $table->string('gateway_reference')->nullable()->comment('External provider transaction id');
            $table->string('status', 32)->default('pending')->index();
            $table->json('status_history')->nullable();
            $table->json('compliance_result')->nullable()->comment('AML / sanctions screening outcome');
            $table->json('gateway_payload')->nullable()->comment('Last response/webhook payload');
            $table->string('purpose')->nullable();
            $table->string('source_of_funds')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['corridor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_transfers');
    }
};
