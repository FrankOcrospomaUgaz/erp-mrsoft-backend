<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->string('kuti_payment_intent_id')->nullable()->index();
            $table->text('kuti_checkout_url')->nullable();
            $table->string('kuti_payment_status', 30)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn(['kuti_payment_intent_id', 'kuti_checkout_url', 'kuti_payment_status']);
        });
    }
};
