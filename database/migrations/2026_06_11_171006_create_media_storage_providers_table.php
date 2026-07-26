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
        Schema::create('media_storage_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver', 32)->default('local');
            $table->string('visibility', 16)->default('public');
            $table->string('prefix')->default('media');
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->text('endpoint')->nullable();
            $table->text('url')->nullable();
            $table->text('root')->nullable();
            $table->text('access_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->boolean('use_path_style_endpoint')->default(false);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('throw')->default(false);
            $table->json('options')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status', 24)->nullable();
            $table->text('last_check_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_storage_providers');
    }
};
