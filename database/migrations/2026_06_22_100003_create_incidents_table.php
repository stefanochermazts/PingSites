<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->default('open');
            $table->string('initial_cause')->nullable();
            $table->string('last_error_type')->nullable();
            $table->unsignedInteger('failed_checks_count')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('last_positive_at')->nullable();
            $table->boolean('public_visible')->default(true);
            $table->text('public_message')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
