<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramBotSeeder extends Seeder
{
    public function run()
    {
        $token = env('TELEGRAM_DEFAULT_BOT_TOKEN');
        if (!$token) {
            $this->command->info('TELEGRAM_DEFAULT_BOT_TOKEN not set in .env — skipping default bot creation.');
            return;
        }

        DB::table('telegram_bots')->updateOrInsert(
            ['username' => env('TELEGRAM_BOT_USERNAME') ?: 'mars_radius_bot'],
            [
                'name' => 'MarsRadiusBot',
                'token' => $token,
                'username' => env('TELEGRAM_BOT_USERNAME'),
                'is_active' => true,
                'settings' => json_encode(['webhook_set' => false]),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Default Telegram bot seeded (if TELEGRAM_DEFAULT_BOT_TOKEN present).');
    }
}
