<?php

use App\Models\Monitor;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Monitor::query()
            ->where(function ($query) {
                $query->whereNull('valid_status_codes')
                    ->orWhereJsonLength('valid_status_codes', 0);
            })
            ->update(['valid_status_codes' => [200, 301, 302]]);
    }

    public function down(): void
    {
        //
    }
};
