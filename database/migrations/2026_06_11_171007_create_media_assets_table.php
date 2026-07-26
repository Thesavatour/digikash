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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_storage_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('extension', 32)->nullable();
            $table->string('mime_type')->nullable();
            $table->string('aggregate_type', 32)->default('other')->index();
            $table->string('disk', 64)->default('media');
            $table->string('driver', 32)->default('local')->index();
            $table->string('visibility', 16)->default('public');
            $table->string('folder')->nullable()->index();
            $table->text('path');
            $table->text('url')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['media_storage_provider_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
