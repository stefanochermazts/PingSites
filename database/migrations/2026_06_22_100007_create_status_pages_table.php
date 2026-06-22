<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->string('slug')->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->foreignId('status_page_id')
                ->nullable()
                ->after('published')
                ->constrained()
                ->nullOnDelete();
        });

        $title = 'Devisia Status';

        if (Schema::hasTable('settings')) {
            $settingsTitle = DB::table('settings')
                ->where('group', 'monitor')
                ->where('name', 'status_page_title')
                ->value('payload');

            if (is_string($settingsTitle)) {
                $decoded = json_decode($settingsTitle, true);
                if (is_string($decoded) && $decoded !== '') {
                    $title = $decoded;
                }
            }
        }

        $defaultPageId = DB::table('status_pages')->insertGetId([
            'name' => 'Principale',
            'title' => $title,
            'slug' => 'devisia',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('monitors')
            ->where('published', true)
            ->update(['status_page_id' => $defaultPageId]);
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_page_id');
        });

        Schema::dropIfExists('status_pages');
    }
};
