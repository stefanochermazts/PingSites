<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('infection_detected_at')->nullable()->after('infection_checked_at');
        });

        DB::table('monitors')
            ->where('is_infected', true)
            ->whereNotNull('infection_checked_at')
            ->update([
                'infection_detected_at' => DB::raw('infection_checked_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('infection_detected_at');
        });
    }
};
