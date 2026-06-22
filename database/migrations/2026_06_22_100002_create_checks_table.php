<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('success');
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('error_type')->nullable();
            $table->string('error_message')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['monitor_id', 'checked_at']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
