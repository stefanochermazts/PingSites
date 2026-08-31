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
            $table->string('cloudways_server_id')->nullable()->after('status_page_id');
            $table->string('cloudways_app_id')->nullable()->after('cloudways_server_id');

            $table->unique(['cloudways_server_id', 'cloudways_app_id']);
        });

        if (Schema::hasTable('settings')) {
            $exists = DB::table('settings')
                ->where('group', 'cloudways')
                ->where('name', 'access_token')
                ->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'group' => 'cloudways',
                    'name' => 'access_token',
                    'payload' => json_encode(''),
                    'locked' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropUnique(['cloudways_server_id', 'cloudways_app_id']);
            $table->dropColumn(['cloudways_server_id', 'cloudways_app_id']);
        });

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('group', 'cloudways')
                ->where('name', 'access_token')
                ->delete();
        }
    }
};
