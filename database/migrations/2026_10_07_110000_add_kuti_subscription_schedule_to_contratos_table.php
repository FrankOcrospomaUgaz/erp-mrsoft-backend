<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (!Schema::hasColumn('contratos', 'kuti_subscription_frequency')) {
                $table->string('kuti_subscription_frequency', 20)->nullable();
            }
            if (!Schema::hasColumn('contratos', 'kuti_subscription_amount')) {
                $table->decimal('kuti_subscription_amount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('contratos', 'kuti_subscription_next_charge_at')) {
                $table->timestamp('kuti_subscription_next_charge_at')->nullable();
            }
            if (!Schema::hasColumn('contratos', 'kuti_subscription_charge_time')) {
                $table->string('kuti_subscription_charge_time', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            foreach ([
                'kuti_subscription_frequency',
                'kuti_subscription_amount',
                'kuti_subscription_next_charge_at',
                'kuti_subscription_charge_time',
            ] as $column) {
                if (Schema::hasColumn('contratos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
