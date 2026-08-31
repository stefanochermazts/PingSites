<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CloudwaysSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('settings')
            ->where('group', 'cloudways')
            ->where('name', 'access_token')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('settings')->insert([
            'group' => 'cloudways',
            'name' => 'access_token',
            'payload' => json_encode(env('CLOUDWAYS_ACCESS_TOKEN', '')),
            'locked' => false,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }
}
