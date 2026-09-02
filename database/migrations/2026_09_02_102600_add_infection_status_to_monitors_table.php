<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('is_infected')->nullable()->after('last_error_type');
            $table->timestamp('infection_checked_at')->nullable()->after('is_infected');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['is_infected', 'infection_checked_at']);
        });
    }
};
