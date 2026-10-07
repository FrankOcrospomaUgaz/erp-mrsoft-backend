<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('kuti_subscription_id')->nullable()->index();
            $table->string('kuti_subscription_status', 30)->nullable()->index();
            $table->text('kuti_subscription_checkout_url')->nullable();
            $table->string('kuti_subscription_frequency', 20)->nullable();
            $table->decimal('kuti_subscription_amount', 12, 2)->nullable();
            $table->timestamp('kuti_subscription_next_charge_at')->nullable();
            $table->string('kuti_subscription_charge_time', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['kuti_subscription_id', 'kuti_subscription_status', 'kuti_subscription_checkout_url', 'kuti_subscription_frequency', 'kuti_subscription_amount', 'kuti_subscription_next_charge_at', 'kuti_subscription_charge_time']);
        });
    }
};
