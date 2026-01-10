<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('conferences:notify-day-before')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();

        $schedule->command('conferences:notify-hour-before')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();

        $schedule->command('conferences:notify')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
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
