<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramBot;

class TelegramService
{
    /**
     * Send a message using specified bot (TelegramBot model)
     * Returns response array or throws exception.
     */
    public function sendMessage(TelegramBot $bot, string $chatId, string $text, array $opts = [])
    {
        $token = $bot->token;
        if (!$token) {
            throw new \Exception('Bot token not configured.');
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $opts['parse_mode'] ?? 'HTML',
        ], $opts['extra'] ?? []);

        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        $json = $resp->json();

        if (!$resp->successful() || !isset($json['ok']) || !$json['ok']) {
            $err = $json['description'] ?? json_encode($json);
            Log::warning("TelegramService: sendMessage failed for chat {$chatId}: {$err}");
            return $json;
        }

        return $json;
    }

    /**
     * Set webhook for the provided bot - returns Telegram API response
     */
    public function setWebhook(TelegramBot $bot, string $url)
    {
        $token = $bot->token;
        if (!$token) throw new \Exception('Bot token not configured.');

        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $url,
            'drop_pending_updates' => true,
        ]);

        return $resp->json();
    }
}
