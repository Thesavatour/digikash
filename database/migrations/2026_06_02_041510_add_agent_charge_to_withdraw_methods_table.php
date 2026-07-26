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
        Schema::table('withdraw_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('withdraw_methods', 'agent_charge')) {
                $table->double('agent_charge')->nullable()->after('merchant_charge_type')->comment('Charge amount for agent users');
                $table->string('agent_charge_type')->default('percent')->after('agent_charge')->comment('Charge type for agent users: fixed or percent (cast to FixPctType enum)');
                $table->index('agent_charge');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdraw_methods', function (Blueprint $table) {
            if (Schema::hasColumn('withdraw_methods', 'agent_charge')) {
                $table->dropIndex(['agent_charge']);
                $table->dropColumn(['agent_charge', 'agent_charge_type']);
            }
        });
    }
};
