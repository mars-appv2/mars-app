<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class TelegramBot {
  protected $api;
  public function __construct() {
    $token = config('services.telegram.bot_token');
    $this->api = "https://api.telegram.org/bot{$token}";
  }

  public function sendMessage($chatId, $text, $opts = []) {
    $payload = array_merge([
      'chat_id' => $chatId,
      'text' => $text,
      'parse_mode' => 'HTML',
      'disable_web_page_preview' => true,
    ], $opts);
    $res = Http::timeout(10)->post("{$this->api}/sendMessage", $payload);
    return $res->json();
  }

  public function sendWithKeyboard($chatId, $text, array $keyboard, $opts = []) {
    $payload = array_merge($opts, [
      'chat_id'=>$chatId,
      'text'=>$text,
      'reply_markup'=> json_encode(['inline_keyboard'=>$keyboard]),
      'parse_mode'=>'HTML',
    ]);
    return Http::post("{$this->api}/sendMessage", $payload)->json();
  }
}
