<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('status')->default('unknown');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('check_frequency')->default(5);
            $table->unsignedSmallInteger('timeout')->default(10);
            $table->json('valid_status_codes')->nullable();
            $table->boolean('follow_redirects')->default(true);
            $table->boolean('verify_ssl')->default(true);
            $table->string('keyword')->nullable();
            $table->unsignedTinyInteger('failure_threshold')->default(2);
            $table->unsignedTinyInteger('recovery_threshold')->default(2);
            $table->boolean('published')->default(false);
            $table->string('public_name')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->unsignedTinyInteger('consecutive_successes')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->unsignedSmallInteger('last_http_code')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->string('last_error_type')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_check_at']);
            $table->index('status');
            $table->index('published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
