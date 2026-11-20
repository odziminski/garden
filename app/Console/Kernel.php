<?php

namespace App\Console;

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

    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('email:care --discover-only --scheduled')
            ->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();

        $schedule->command('email:care --pending-only --scheduled')
            ->everyMinute()->withoutOverlapping(5)->runInBackground();

        $schedule->command('photos:cleanup')
            ->everyMinute()->withoutOverlapping(5)->runInBackground();

        $schedule->command('photos:identify')
            ->everyMinute()->withoutOverlapping(5)->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
