<?php

/*
 * Excerpt: app/Console/Kernel.php (Fix and Free). Only the schedule entry for
 * CheckCustomerPackageExpiration is shown: it runs once a day at 12:00 server time.
 */

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('check:customerpackageexpiration')->cron('0 12 * * *');
    }
}
