<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\TelegramBot;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $botId;
    public $chatId;
    public $text;
    public $opts;

    public function __construct($botId, $chatId, $text, $opts = [])
    {
        $this->botId = $botId;
        $this->chatId = $chatId;
        $this->text = $text;
        $this->opts = $opts;
    }

    public function handle(TelegramService $telegram)
    {
        $bot = TelegramBot::find($this->botId);
        if (!$bot) {
            Log::warning("SendTelegramMessage: bot {$this->botId} not found.");
            return;
        }

        try {
            $telegram->sendMessage($bot, $this->chatId, $this->text, $this->opts);
        } catch (\Throwable $e) {
            Log::error("SendTelegramMessage failed: ".$e->getMessage());
        }
    }
}
