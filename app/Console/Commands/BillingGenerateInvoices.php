<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingGenerateInvoices extends Command
{
    protected $signature = 'billing:generate {--period=} {--due-days=10} {--ids=*}';
    //                               ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    // bisa kirim daftar subscription id (opsional)
    protected $description = 'Generate invoices untuk subscription aktif';

    public function handle(): int
    {
        $period  = $this->option('period') ?: date('Y-m'); // YYYY-MM
        $dueDays = max(1, (int)$this->option('due-days'));
        $onlyIds = array_filter((array)$this->option('ids')); // array of sid

        $hasPriceMonth = Schema::hasColumn('plans', 'price_month');
        $hasPrice      = Schema::hasColumn('plans', 'price');

        $subs = DB::table('subscriptions as s')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->select(
                's.id as sid','s.username','s.mikrotik_id',
                'p.id as pid','p.name as plan_name','p.price_month','p.price'
            )
            ->when(!empty($onlyIds), fn($q)=>$q->whereIn('s.id',$onlyIds))
            ->when(Schema::hasColumn('subscriptions','status'), fn($q)=>$q->where('s.status','active'))
            ->orderBy('s.id')
            ->limit(10000)
            ->get();

        $created = 0;

        foreach ($subs as $row) {
            $exists = DB::table('invoices')
                ->where('subscription_id', $row->sid)
                ->where('period', $period)
                ->exists();
            if ($exists) continue;

            // Hitung amount
            $amount = 0;
            if ($hasPriceMonth && $row->price_month !== null) $amount = (int)$row->price_month;
            elseif ($hasPrice && $row->price !== null)        $amount = (int)$row->price;

            // Siapkan payload dasar
            $payload = [
                'subscription_id' => $row->sid,
                'number'          => $this->nextNumber(),
                'amount'          => $amount,
                'status'          => 'unpaid',
                'period'          => $period,
                'due_date'        => now()->addDays($dueDays),
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            // ====== Isi kolom opsional jika ada di tabel ======
            if (Schema::hasColumn('invoices','customer_name')) $payload['customer_name'] = $row->username;
            if (Schema::hasColumn('invoices','mikrotik_id'))   $payload['mikrotik_id']   = $row->mikrotik_id;
            if (Schema::hasColumn('invoices','plan_id'))       $payload['plan_id']       = $row->pid;
            if (Schema::hasColumn('invoices','plan_name'))     $payload['plan_name']     = $row->plan_name;

            // angka2 turunan: subtotal/tax/discount/total (bila kolomnya ada)
            $subtotal = $amount;
            $tax      = 0;
            $discount = 0;
            if (Schema::hasColumn('invoices','subtotal')) $payload['subtotal'] = $subtotal;
            if (Schema::hasColumn('invoices','tax'))      $payload['tax']      = $tax;
            if (Schema::hasColumn('invoices','discount')) $payload['discount'] = $discount;
            if (Schema::hasColumn('invoices','total'))    $payload['total']    = $subtotal + $tax - $discount;
            // ================================================

            DB::table('invoices')->insert($payload);
            $created++;
        }

        $this->info("Generated {$created} invoice(s) for period {$period}.");
        return self::SUCCESS;
    }

    private function nextNumber(): string
    {
        $prefix = 'INV'.date('Ym').'-';
        $last = DB::table('invoices')->where('number','like',$prefix.'%')->max('number');
        $seq = 1;
        if ($last) { $seq = (int)substr($last, -4); $seq = $seq ? $seq + 1 : 1; }
        return $prefix.str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}
