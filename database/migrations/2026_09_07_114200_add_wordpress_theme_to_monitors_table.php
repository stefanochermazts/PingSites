<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('wordpress_theme')->nullable()->after('infection_checked_at');
            $table->string('wordpress_theme_slug')->nullable()->after('wordpress_theme');
            $table->timestamp('wordpress_theme_checked_at')->nullable()->after('wordpress_theme_slug');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn([
                'wordpress_theme',
                'wordpress_theme_slug',
                'wordpress_theme_checked_at',
            ]);
        });
    }
};
