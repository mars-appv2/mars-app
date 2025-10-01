namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TelegramBot;
use App\Models\TelegramSubscriber;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, $secret)
    {
        if ($secret !== env('TELEGRAM_WEBHOOK_SECRET')) {
            abort(403, 'Invalid secret');
        }

        $update = $request->all();
        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $text   = $update['message']['text'] ?? '';

            // Simpan subscriber baru
            TelegramSubscriber::firstOrCreate(
                ['chat_id' => $chatId],
                [
                    'first_name' => $update['message']['chat']['first_name'] ?? '',
                    'last_name'  => $update['message']['chat']['last_name'] ?? '',
                    'username'   => $update['message']['chat']['username'] ?? '',
                ]
            );

            // Respon awal
            \Telegram::sendMessage([
                'chat_id' => $chatId,
                'text'    => "Selamat datang di Radius MDNet. Anda akan menerima notifikasi tagihan & koneksi."
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
