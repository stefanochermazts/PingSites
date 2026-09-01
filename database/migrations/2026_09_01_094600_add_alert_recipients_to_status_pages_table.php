<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_pages', function (Blueprint $table) {
            $table->text('alert_recipients')->nullable()->after('is_default');
        });

        $globalRecipients = $this->globalAlertRecipients();

        if ($globalRecipients !== null) {
            DB::table('status_pages')
                ->whereNull('alert_recipients')
                ->update(['alert_recipients' => $globalRecipients]);
        }
    }

    public function down(): void
    {
        Schema::table('status_pages', function (Blueprint $table) {
            $table->dropColumn('alert_recipients');
        });
    }

    private function globalAlertRecipients(): ?string
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        $payload = DB::table('settings')
            ->where('group', 'monitor')
            ->where('name', 'alert_recipients')
            ->value('payload');

        if (! is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        return $decoded;
    }
};
