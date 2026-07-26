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
        Schema::table('withdraw_methods', function (Blueprint $table): void {
            if (Schema::hasColumn('withdraw_methods', 'icon') && ! Schema::hasColumn('withdraw_methods', 'logo')) {
                $table->renameColumn('icon', 'logo');
            }

            if (Schema::hasColumn('withdraw_methods', 'code') && ! Schema::hasColumn('withdraw_methods', 'method_code')) {
                $table->renameColumn('code', 'method_code');
            }

            if (Schema::hasColumn('withdraw_methods', 'min_limit') && ! Schema::hasColumn('withdraw_methods', 'min_withdraw')) {
                $table->renameColumn('min_limit', 'min_withdraw');
            }

            if (Schema::hasColumn('withdraw_methods', 'max_limit') && ! Schema::hasColumn('withdraw_methods', 'max_withdraw')) {
                $table->renameColumn('max_limit', 'max_withdraw');
            }

            if (Schema::hasColumn('withdraw_methods', 'rate') && ! Schema::hasColumn('withdraw_methods', 'conversion_rate')) {
                $table->renameColumn('rate', 'conversion_rate');
            }
        });

        Schema::table('withdraw_methods', function (Blueprint $table): void {
            if (! Schema::hasColumn('withdraw_methods', 'conversion_rate_live')) {
                $column = $table->boolean('conversion_rate_live')->nullable();

                if (Schema::hasColumn('withdraw_methods', 'rate_type')) {
                    $column->after('rate_type');
                }
            }

            if (Schema::hasColumn('withdraw_methods', 'rate_type')) {
                $table->dropColumn('rate_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdraw_methods', function (Blueprint $table): void {
            if (Schema::hasColumn('withdraw_methods', 'logo') && ! Schema::hasColumn('withdraw_methods', 'icon')) {
                $table->renameColumn('logo', 'icon');
            }

            if (Schema::hasColumn('withdraw_methods', 'method_code') && ! Schema::hasColumn('withdraw_methods', 'code')) {
                $table->renameColumn('method_code', 'code');
            }

            if (Schema::hasColumn('withdraw_methods', 'min_withdraw') && ! Schema::hasColumn('withdraw_methods', 'min_limit')) {
                $table->renameColumn('min_withdraw', 'min_limit');
            }

            if (Schema::hasColumn('withdraw_methods', 'max_withdraw') && ! Schema::hasColumn('withdraw_methods', 'max_limit')) {
                $table->renameColumn('max_withdraw', 'max_limit');
            }

            if (Schema::hasColumn('withdraw_methods', 'conversion_rate') && ! Schema::hasColumn('withdraw_methods', 'rate')) {
                $table->renameColumn('conversion_rate', 'rate');
            }

            if (Schema::hasColumn('withdraw_methods', 'conversion_rate_live')) {
                $table->dropColumn('conversion_rate_live');
            }

            if (! Schema::hasColumn('withdraw_methods', 'rate_type')) {
                $table->enum('rate_type', ['fixed', 'live'])->default('fixed');
            }
        });
    }
};
