<?php

namespace App\Console;

use App\Enums\NotificationPriority;
use App\Enums\PreSolicitudStatus;
use App\Helpers\JobsHelper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('inspire')->hourly();

        $schedule->call(function() {
            (new JobsHelper())::rePushNotification(NotificationPriority::URGENT);
        })
        ->name('PushNotifications UnRead')
        ->withoutOverlapping()
        ->everyMinute();

        $schedule->call(function() {
            (new JobsHelper())::expireNotAttendedPreSol(3, 'Set Notification for PreSol after 2 min with out payment', PreSolicitudStatus::OPERADORASIGNADO, true);
        })
        ->name('Set Notification for PreSol after 3 min with out payment')
        ->withoutOverlapping()
        ->everyThreeMinutes();

        $schedule->call(function() {
            (new JobsHelper())::expireNotAttendedPreSol(5, 'Set PreSol to expired after 5 min with out payment', PreSolicitudStatus::OPERADORASIGNADO, true);
        })
        ->name('Set PreSol to expired after 5 min with out payment')
        ->withoutOverlapping()
        ->everyFiveMinutes();

        // $schedule->call(function() {
        //     (new JobsHelper())::expireNotAttendedPreSol(15, 'Set PreSol to expired after 15 min with out attend after operator atteched to request', PreSolicitudStatus::OPERADORASIGNADO, true);
        // })
        // ->name('Set PreSol to expired after 15 min with out attend after operator atteched to request')
        // ->withoutOverlapping()
        // ->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
