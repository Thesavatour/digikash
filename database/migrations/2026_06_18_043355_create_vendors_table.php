<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('commission_pct_override', 8, 4)->nullable();
            $table->text('about')->nullable();
            // Forward-looking columns for Phase 2 gateway-level split (Stripe Connect).
            $table->string('stripe_connect_account_id')->nullable();
            $table->string('stripe_onboarding_status', 30)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
