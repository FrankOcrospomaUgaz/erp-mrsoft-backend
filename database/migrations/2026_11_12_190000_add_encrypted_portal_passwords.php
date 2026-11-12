<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'password_encrypted')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->text('password_encrypted')->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'password_encrypted')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('password_encrypted');
            });
        }
    }
};
