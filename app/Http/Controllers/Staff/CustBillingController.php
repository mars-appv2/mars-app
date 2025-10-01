<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustBillingController extends Controller
{
    public function index($customer)
    {
        $c = DB::table('customers')->where('id',$customer)->first();
        abort_unless($c, 404);
        $invoices = DB::table('cust_invoices')->where('customer_id',$c->id)->orderByDesc('id')->get();
        return view('staff.customers.invoices', compact('c','invoices'));
    }

    public function storeInvoice(Request $r, $customer)
    {
        $r->validate([
            'amount'=>'required|integer|min:0',
            'bill_date'=>'required|date',
            'due_date'=>'nullable|date'
        ]);

        $c = DB::table('customers')->where('id',$customer)->first();
        abort_unless($c, 404);

        DB::table('cust_invoices')->insert([
            'customer_id'=>$c->id,
            'amount'=>$r->amount,
            'bill_date'=>$r->bill_date,
            'due_date'=>$r->due_date,
            'status'=>'unpaid',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        return back()->with('ok','Invoice dibuat.');
    }

    public function pay(Request $r, $invoice)
    {
        $r->validate([
            'amount'=>'required|integer|min:0',
            'method'=>'nullable|string',
            'ref_no'=>'nullable|string|max:64',
        ]);

        $inv = DB::table('cust_invoices')->where('id',$invoice)->first();
        abort_unless($inv, 404);

        DB::table('cust_payments')->insert([
            'invoice_id'=>$inv->id,
            'amount'=>$r->amount,
            'method'=>$r->method,
            'ref_no'=>$r->ref_no,
            'paid_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        // Tandai LUNAS
        DB::table('cust_invoices')->where('id',$inv->id)->update(['status'=>'paid','updated_at'=>now()]);

        // Pastikan layanan AKTIF setelah bayar
        $customer = DB::table('customers')->where('id',$inv->customer_id)->first();
        app(\App\Services\Provisioner::class)->activate($customer);

        return back()->with('ok','Pembayaran dicatat & layanan aktif.');
    }
}
