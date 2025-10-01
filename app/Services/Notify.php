<?php
namespace App\Services;
use App\Models\Setting;
class Notify{
  public static function tg($text){
    $token = optional(Setting::firstWhere('key','telegram_token'))->value;
    $chats = optional(Setting::firstWhere('key','telegram_chat_ids'))->value;
    if(!$token || !$chats) return;
    $ids = array_map('trim', explode(',', $chats));
    foreach($ids as $id){
      try{
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        @file_get_contents($url.'?'.http_build_query(['chat_id'=>$id,'text'=>$text]));
      }catch(\Throwable $e){}
    }
  }
}
