<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Daftar command berbasis class yang disediakan aplikasi.
     * (Pastikan class-class di bawah memang ada di app/Console/Commands)
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\BillingSyncSubs::class,
        \App\Console\Commands\BillingGenerateInvoices::class,
        \App\Console\Commands\BillingEnforce::class,
        \App\Console\Commands\TrafficSampleQueue::class,
        \App\Console\Commands\RebuildPppSecrets::class, // <-- yang kita tambahkan
	\App\Console\Commands\MikrotikBackupRun::class,
    ];

    /**
     * Jadwal task.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Hindari duplikasi baris; pilih frekuensi yang kamu mau.
        $schedule->command('billing:sync-subs')->dailyAt('00:30');
        $schedule->command('billing:generate')->dailyAt('00:40');

        // Kamu tadi punya 2x hourly + everyFifteenMinutes.
        // Kita pakai yang lebih sering saja biar konsisten:
        $schedule->command('billing:enforce')->everyFifteenMinutes()->withoutOverlapping();
	$schedule->command('mikrotik:backup --all')->dailyAt('02:10')->withoutOverlapping();
	$schedule->command('tickets:auto-close-psb')->everyMinute()->withoutOverlapping();
	$schedule->command('psb:auto-close')->everyMinute();
	$schedule->command('wa:broadcast:tick')->everyMinute();
        // Contoh lain (kalau mau tetap hourly, aktifkan baris ini dan matikan yang every 15 menit):
        // $schedule->command('billing:enforce')->hourly()->withoutOverlapping();
    }

    /**
     * Registrasi commands.
     *
     * @return void
     */
    protected function commands()
    {
        // Muat command-class dari folder Commands
        $this->load(__DIR__.'/Commands');

        // Muat command berbasis closure dari routes/console.php (kalau ada)
        require base_path('routes/console.php');

    }
    protected $routeMiddleware = [
    	'role_or_pass' => \App\Http\Middleware\RoleOrPass::class,  // ← tambahkan di sini
    ];


}
