<?php

use App\Models\Language;
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
        Schema::table('languages', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('status')->index();
        });

        Language::query()
            ->orderBy('id')
            ->get(['id'])
            ->each(function (Language $language, int $index): void {
                $language->forceFill(['sort_order' => $index + 1])->saveQuietly();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
