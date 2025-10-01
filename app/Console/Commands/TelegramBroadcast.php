<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TelegramBot;

class TelegramBroadcast extends Command
{
    protected $signature = 'telegram:broadcast {message*} {--to=* : chat_id target (boleh banyak)}';
    protected $description = 'Broadcast pesan ke subscriber Telegram';

    public function handle(TelegramBot $bot)
    {
        $text = implode(' ', $this->argument('message'));
        if (!$text) { $this->error('Pesan kosong'); return 1; }

        $targets = $this->option('to');
        if (empty($targets)) {
            $targets = DB::table('telegram_subscribers')->where('active',1)->pluck('chat_id')->all();
            if (empty($targets) && env('TELEGRAM_DEFAULT_CHAT_ID')) {
                $targets = [env('TELEGRAM_DEFAULT_CHAT_ID')];
            }
        }

        $ok = 0;
        foreach ($targets as $chatId) {
            $res = $bot->sendMessage((string)$chatId, $text);
            $ok += !empty($res['ok']);
            usleep(200000); // 0.2s jeda hindari limit
        }
        $this->info("Terkirim: {$ok}/".count($targets));
        return 0;
    }
}
