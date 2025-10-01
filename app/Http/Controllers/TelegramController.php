<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\TelegramBot;
use App\Models\TelegramSubscriber;
use App\Services\TelegramService;
use App\Jobs\SendTelegramMessage;
use Illuminate\Support\Facades\Queue;

class TelegramController extends Controller
{
    public function index()
    {
        $bots = Schema::hasTable('telegram_bots') ? TelegramBot::orderByDesc('id')->get() : collect();
        return view('telegram.index', compact('bots'));
    }

    public function settingsPage()
    {
        $bots = Schema::hasTable('telegram_bots') ? TelegramBot::orderByDesc('id')->get() : collect();
        return view('settings.telegram', compact('bots'));
    }

    public function settingsSave(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:telegram_bots,id',
            'name' => 'required|string|max:191',
            'username' => 'nullable|string|max:191',
            'token' => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'token' => $request->token, // model akan encrypt
            'is_active' => (bool) $request->input('is_active', true),
            'settings' => $request->input('settings', []),
        ];

        if ($request->filled('id')) {
            $bot = TelegramBot::find($request->id);
            $bot->update($data);
            $msg = 'Bot diperbarui.';
        } else {
            $bot = TelegramBot::create($data);
            $msg = 'Bot dibuat.';
        }

        return redirect()->route('settings.telegram')->with('success', $msg);
    }

    public function destroyBot($id)
    {
        $bot = TelegramBot::findOrFail($id);
        $bot->delete();
        return redirect()->route('settings.telegram')->with('success', 'Bot dihapus.');
    }

    public function setWebhook(Request $request, $id, TelegramService $telegram)
    {
        $bot = TelegramBot::findOrFail($id);
        $secret = env('TELEGRAM_WEBHOOK_SECRET');
        if (!$secret) {
            return back()->with('err','TELEGRAM_WEBHOOK_SECRET belum diatur di .env');
        }

        $url = rtrim(config('app.url'), '/') . '/telegram/' . $secret;

        try {
            $resp = $telegram->setWebhook($bot, $url);
            if (isset($resp['ok']) && $resp['ok']) {
                $settings = $bot->settings ?? [];
                $settings['webhook_set'] = true;
                $bot->settings = $settings;
                $bot->save();
                return back()->with('success','Webhook berhasil di-set.');
            }
            return back()->with('err','Gagal set webhook: '.json_encode($resp));
        } catch (\Throwable $e) {
            Log::error('setWebhook: '.$e->getMessage());
            return back()->with('err','Gagal set webhook: '.$e->getMessage());
        }
    }

    public function broadcast(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'bot_id' => 'required|exists:telegram_bots,id',
        ]);

        $bot = TelegramBot::find($request->bot_id);
        if (!$bot) return back()->with('err','Bot tidak ditemukan.');

        $subs = $bot->subscribers()->where('is_active', true)->get();
        $failed = 0;
        $queued = 0;

        foreach ($subs as $sub) {
            try {
                // jika queue disetup -> dispatch job, lainwse send sync
                if (app()->bound('queue')) {
                    SendTelegramMessage::dispatch($bot->id, $sub->chat_id, $request->message);
                    $queued++;
                } else {
                    // fallback sync: gunakan service langsung
                    app(TelegramService::class)->sendMessage($bot, $sub->chat_id, $request->message);
                }
            } catch (\Throwable $e) {
                Log::error("Broadcast to {$sub->chat_id} failed: ".$e->getMessage());
                $failed++;
            }
        }

        if ($failed > 0) {
            return back()->with('warning', "Broadcast selesai — {$queued} queued, {$failed} gagal.");
        }

        if ($queued > 0) {
            return back()->with('success', "Broadcast ditambahkan ke queue ({$queued} pesan).");
        }

        return back()->with('success','Broadcast terkirim.');
    }
}
