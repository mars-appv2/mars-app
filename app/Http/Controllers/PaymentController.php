<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Notify;

class PaymentController extends Controller{
  private function midtransConfig(){
    $server = env('MIDTRANS_SERVER_KEY') ?: optional(\App\Models\Setting::firstWhere('key','midtrans_server_key'))->value;
    $client = env('MIDTRANS_CLIENT_KEY') ?: optional(\App\Models\Setting::firstWhere('key','midtrans_client_key'))->value;
    $prod   = env('MIDTRANS_IS_PRODUCTION') !== null
              ? filter_var(env('MIDTRANS_IS_PRODUCTION'), FILTER_VALIDATE_BOOLEAN)
              : (optional(\App\Models\Setting::firstWhere('key','midtrans_is_production'))->value==='1');
    if(!$server){ abort(500,'Midtrans server key not set'); }
    \Midtrans\Config::$serverKey = $server;
    \Midtrans\Config::$isProduction = $prod;
    \Midtrans\Config::$isSanitized = true;
    \Midtrans\Config::$is3ds = true;
    return [$server,$client,$prod];
  }
  public function pay(Invoice $invoice){
    if($invoice->status==='paid'){ return redirect()->route('billing.index'); }
    $this->midtransConfig();
    $orderId = $invoice->number.'-'.time();
    $params = [
      'transaction_details' => ['order_id'=>$orderId, 'gross_amount'=>$invoice->total],
      'customer_details'    => ['first_name'=>$invoice->customer_name],
      'callbacks'           => ['finish'=>route('billing.index')],
      'enabled_payments'    => ['qris','gopay','bca_va','bni_va','bri_va','permata_va'],
      'expiry'              => ['unit'=>'days','duration'=>2],
    ];
    $snap = \Midtrans\Snap::createTransaction($params);
    Payment::create([
      'invoice_id'=>$invoice->id,'gateway'=>'midtrans',
      'order_id'=>$orderId,'amount'=>$invoice->total,'status'=>'pending','payload'=>$snap
    ]);
    return redirect()->away($snap->redirect_url);
  }
  public function notify(Request $r){
    $server = env('MIDTRANS_SERVER_KEY') ?: optional(\App\Models\Setting::firstWhere('key','midtrans_server_key'))->value;
    $order_id = $r->input('order_id'); $status_code=$r->input('status_code'); $gross_amount=$r->input('gross_amount'); $signature=$r->input('signature_key');
    $expected = hash('sha512', $order_id.$status_code.$gross_amount.$server);
    if($signature !== $expected){ return response('invalid signature',403); }
    $payment = Payment::where('order_id',$order_id)->first(); if(!$payment){ return response('unknown order',404); }
    $transaction_status = $r->input('transaction_status');
    if(in_array($transaction_status,['capture','settlement'])){
      $payment->update(['status'=>'paid','payload'=>$r->all()]);
      $inv=$payment->invoice; if($inv && $inv->status!=='paid'){ $inv->update(['status'=>'paid','paid_at'=>now()]); \App\Models\Cashflow::create(['type'=>'in','amount'=>$inv->total,'note'=>"Payment {$inv->number}",'date'=>now(),'invoice_id'=>$inv->id]); Notify::tg("Invoice {$inv->number} dibayar via Midtrans"); }
    } elseif(in_array($transaction_status,['deny','expire','cancel'])){ $payment->update(['status'=>'failed','payload'=>$r->all()]); }
    else { $payment->update(['payload'=>$r->all()]); }
    return response('OK',200);
  }
}
