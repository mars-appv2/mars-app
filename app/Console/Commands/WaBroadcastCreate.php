<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\WaBroadcast;
use App\Models\WaBroadcastRecipient;

class WaBroadcastCreate extends Command
{
    // Tidak ada opsi sama sekali -> aman dari konflik
    protected $signature = 'wa:broadcast:create
                            {text : Pesan broadcast}
                            {targets* : Nomor (spasi dipisah) atau item diawali @/path/file.txt}';

    protected $description = 'Buat job broadcast WA (rate per menit via ENV WA_BROADCAST_RATE, default 5)';

    public function handle(): int
    {
        $text = (string) $this->argument('text');
        $rpm  = (int) env('WA_BROADCAST_RATE', 5);
        if ($rpm < 1) $rpm = 5;

        $targets = collect($this->argument('targets') ?? []);

        // Pisahkan token @file dan nomor langsung
        $files  = $targets->filter(fn($t) => is_string($t) && strlen($t) > 1 && $t[0] === '@')
                          ->map(fn($t) => substr($t,1))
                          ->values();
        $nums   = $targets->reject(fn($t) => is_string($t) && strlen($t) > 1 && $t[0] === '@')
                          ->values();

        // Baca file-file jika ada
        $fileNums = collect();
        foreach ($files as $path) {
            if (!is_readable($path)) {
                $this->error("File tidak bisa dibaca: {$path}");
                return 1;
            }
            $arr = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $fileNums = $fileNums->concat($arr);
        }

        // Normalisasi nomor (hanya digit)
        $numbers = $nums->concat($fileNums)
                        ->map(fn($n) => preg_replace('/\D/','', (string)$n))
                        ->filter()
                        ->unique()
                        ->values();

        if ($numbers->isEmpty()) {
            $this->error('Tidak ada nomor.');
            return 1;
        }

        DB::beginTransaction();
        try {
            $job = WaBroadcast::create([
                'text'         => $text,
                'rate_per_min' => $rpm,
                'status'       => 'pending',
                'total'        => $numbers->count(),
                'sent'         => 0,
                'failed'       => 0,
                'next_run_at'  => now(),
            ]);

            // Bulk insert recipients (chunk biar ringan)
            foreach ($numbers->chunk(1000) as $chunk) {
                WaBroadcastRecipient::insert($chunk->map(fn($p) => [
                    'wa_broadcast_id' => $job->id,
                    'phone'           => $p,
                    'status'          => 'pending',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ])->all());
            }

            DB::commit();
            $this->info("Job #{$job->id} dibuat. Total: {$job->total}. Rate: {$rpm}/menit.");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal membuat job: '.$e->getMessage());
            return 1;
        }
    }
}
