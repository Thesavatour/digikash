<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname')->nullable();
            $table->string('country_code', 2)->index();
            $table->string('currency', 3)->index();
            $table->string('payout_method', 32);
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->json('account_details');
            $table->string('relationship')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
