<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WaService;

class AutoClosePsbTickets extends Command
{
    protected $signature = 'psb:auto-close {--once : Run only one iteration}';
    protected $description = 'Auto-close tiket PSB ketika user sudah aktif (radacct/ppp active)';

    public function handle()
    {
        if (!filter_var(env('PSB_AUTOCLOSE_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Auto-close PSB disabled (PSB_AUTOCLOSE_ENABLED=false)');
            return 0;
        }

        $lookback = (int) env('PSB_AUTOCLOSE_LOOKBACK_MINUTES', 240); // 4 jam default
        $since = now()->subMinutes($lookback);

        // Ambil tiket PSB OPEN baru-baru ini
        $q = DB::table('tickets')
            ->where('type', 'psb')
            ->where('status', 'open');

        if ($lookback > 0) {
            $q->where('created_at', '>=', $since);
        }

        $tickets = $q->orderBy('id')->get(['id','code','username','created_at']);

        if ($tickets->isEmpty()) {
            $this->line('No open PSB tickets to check.');
            return 0;
        }

        // Pakai logic parity dari WaBotController::isUserOnline()
        /** @var \App\Http\Controllers\WaBotController $bot */
        $bot = app(\App\Http\Controllers\WaBotController::class);

        $closed = 0;
        foreach ($tickets as $t) {
            $u = (string) ($t->username ?? '');
            if ($u === '') continue;

            try {
                if ($bot->isUserOnline($u)) {
                    $note = "Auto-close: user {$u} terdeteksi AKTIF.";
                    $this->closeTicket($t->id, $note);

                    // Broadcast penutup
                    $msg = "[TIKET {$t->code}] PSB CLOSED (auto)\nUser {$u} sudah aktif.";
                    $this->notifyNocTeknisi($msg);

                    Log::info('PSB-AUTO closed', ['code' => $t->code, 'username' => $u]);
                    $closed++;
                }
            } catch (\Throwable $e) {
                Log::warning('PSB-AUTO error: '.$e->getMessage(), ['ticket_id' => $t->id]);
            }
        }

        $this->info("Checked {$tickets->count()} tickets, closed {$closed}.");
        return 0;
    }

    private function closeTicket(int $id, string $note = ''): void
    {
        $t = DB::table('tickets')->where('id', $id)->first();
        if (!$t || $t->status === 'closed') return;

        $data = ['status' => 'closed', 'updated_at' => now()];
        if (Schema()->hasColumn('tickets', 'closed_at')) $data['closed_at'] = now();
        if (Schema()->hasColumn('tickets', 'closed_by')) $data['closed_by'] = 0;

        if ($note !== '') {
            if (Schema()->hasColumn('tickets', 'resolution')) {
                $data['resolution'] = $note;
            } else {
                $data['description'] = trim((string)$t->description."\n[Closed] ".$note);
            }
        }

        DB::table('tickets')->where('id', $id)->update($data);
    }

    private function notifyNocTeknisi(string $msg): void
    {
        $phones = DB::table('wa_staff')
            ->where('active', 1)
            ->whereIn(DB::raw('LOWER(role)'), ['noc','teknisi'])
            ->pluck('phone')->map(fn($p) => preg_replace('/\D/', '', (string)$p))
            ->filter()->unique()->values()->all();

        if (!$phones) return;

        $wa = new WaService();
        foreach ($phones as $p) {
            $wa->sendText($p, $msg);
            usleep(200 * 1000);
        }
    }
}
