<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('public_visible')->default(true);
            $table->text('public_message')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('maintenance_window_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();

            $table->unique(['maintenance_window_id', 'monitor_id'], 'mw_monitor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_window_monitor');
        Schema::dropIfExists('maintenance_windows');
    }
};
