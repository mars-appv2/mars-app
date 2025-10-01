<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\TelegramBot;
use App\Models\TelegramSubscriber;



class TelegramWebhookController extends Controller
{
    public function handle(Request $request, $secret = null)
    {
        $expected = env('TELEGRAM_WEBHOOK_SECRET');
        if (!$expected || $secret !== $expected) {
            Log::warning('Telegram webhook invalid secret: '.$secret);
            return response()->json(['ok' => false, 'error' => 'invalid_secret'], 403);
        }

        $update = $request->all();
        Log::debug('Telegram webhook update: '.json_encode($update));

        if (!Schema::hasTable('telegram_bots') || !Schema::hasTable('telegram_subscribers')) {
            Log::warning('Telegram webhook: tables missing');
            return response()->json(['ok' => false, 'error' => 'tables_missing'], 500);
        }

        // Ambil bot default (yang aktif dan webhook_set true) — pilih paling recent
        $bot = TelegramBot::where('is_active', true)
                ->whereRaw("JSON_EXTRACT(COALESCE(settings, '{}'), '$.webhook_set') = true")
                ->orderByDesc('id')->first();

        // fallback: bot aktif pertama
        if (!$bot) {
            $bot = TelegramBot::where('is_active', true)->orderByDesc('id')->first();
        }

        if (!$bot) {
            Log::warning('Telegram webhook: no bot configured');
            return response()->json(['ok' => false, 'error' => 'no_bot'], 500);
        }

        // ambil info "from"
        $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? null;
        $from = $message['from'] ?? $update['callback_query']['from'] ?? $update['my_chat_member']['from'] ?? null;

        if (!$from || !isset($from['id'])) {
            Log::warning('Telegram webhook: no from id in update');
            return response()->json(['ok' => true]);
        }

        $chatId = (string) ($update['message']['chat']['id'] ?? $from['id']);

        try {
            $sub = TelegramSubscriber::updateOrCreate(
                ['bot_id' => $bot->id, 'chat_id' => $chatId],
                [
                    'user_id' => null,
                    'first_name' => $from['first_name'] ?? null,
                    'last_name' => $from['last_name'] ?? null,
                    'username' => $from['username'] ?? null,
                    'language_code' => $from['language_code'] ?? null,
                    'is_active' => true,
                    'meta' => $update,
                ]
            );

            if ($sub->wasRecentlyCreated) {
                // optional: kirim welcome (non-blocking)
                @file_get_contents("https://api.telegram.org/bot{$bot->token}/sendMessage?" . http_build_query([
                    'chat_id' => $chatId,
                    'text' => "Terima kasih sudah terhubung ke {$bot->name}. Anda akan menerima notifikasi."
                ]));
            }
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookController save fail: '.$e->getMessage());
            return response()->json(['ok' => false, 'error' => 'save_failed'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
