<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\WaBroadcast;
use App\Models\WaBroadcastRecipient;
use App\Services\WaService;

class WaBroadcastTick extends Command
{
    protected $signature = 'wa:broadcast:tick';
    protected $description = 'Proses broadcast WA sesuai rate-limit (jalankan tiap menit via scheduler)';

    public function handle(): int
    {
        $now = now();

        // ambil semua job yang waktunya jalan
        $jobs = WaBroadcast::whereIn('status', ['pending','running'])
            ->where(function($q) use ($now){
                $q->whereNull('next_run_at')->orWhere('next_run_at','<=',$now);
            })
            ->orderBy('id')
            ->get();

        if ($jobs->isEmpty()) {
            $this->line('No due jobs.');
            return 0;
        }

        $wa = new WaService();

        foreach ($jobs as $job) {
            $limit = max(1, (int)$job->rate_per_min);

            // lock ringan: set running
            if ($job->status !== 'running') {
                $job->status = 'running';
                $job->save();
            }

            // ambil penerima pending
            $list = WaBroadcastRecipient::where('wa_broadcast_id', $job->id)
                ->where('status','pending')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            if ($list->isEmpty()) {
                // selesai
                $job->status = 'done';
                $job->next_run_at = null;
                $job->save();
                $this->info("Job #{$job->id} selesai. sent={$job->sent} failed={$job->failed}");
                continue;
            }

            $sent=0; $fail=0;

            foreach ($list as $rcpt) {
                try {
                    $ok = $wa->sendText($rcpt->phone, $job->text);
                    if ($ok) {
                        $rcpt->status  = 'sent';
                        $rcpt->sent_at = now();
                        $rcpt->last_error = null;
                        $rcpt->save();
                        $sent++;
                    } else {
                        $rcpt->status = 'failed';
                        $rcpt->last_error = 'gateway non-2xx';
                        $rcpt->save();
                        $fail++;
                    }
                } catch (\Throwable $e) {
                    $rcpt->status = 'failed';
                    $rcpt->last_error = $e->getMessage();
                    $rcpt->save();
                    $fail++;
                }

                // jeda kecil agar tidak nabrak (opsional)
                usleep(200 * 1000); // 0.2s
            }

            // update agregat job
            $job->sent   = (int) WaBroadcastRecipient::where('wa_broadcast_id',$job->id)->where('status','sent')->count();
            $job->failed = (int) WaBroadcastRecipient::where('wa_broadcast_id',$job->id)->where('status','failed')->count();

            // jika masih ada pending → jalan lagi 1 menit kemudian
            $pendingLeft = WaBroadcastRecipient::where('wa_broadcast_id',$job->id)->where('status','pending')->count();
            if ($pendingLeft > 0) {
                $job->next_run_at = now()->addMinute();
                $job->status = 'running';
            } else {
                $job->next_run_at = null;
                $job->status = 'done';
            }
            $job->save();

            $this->info("Job #{$job->id}: sent+={$sent} fail+={$fail} pending={$pendingLeft}");
        }

        return 0;
    }
}
