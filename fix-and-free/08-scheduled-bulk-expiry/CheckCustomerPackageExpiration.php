<?php

/*
 * Source: app/Console/Commands/CheckCustomerPackageExpiration.php
 * (one stray `;` line removed; otherwise as shipped)
 *
 * Context: Fix and Free: subscription/visit system for a gym, salon and spa business
 * (Laravel 7, PHP 7.4, MySQL), built 2020-2021. Private repo.
 *
 * Problem solved: expire salon, gym and spa services once their end date passes.
 * It runs daily from the scheduler (see Kernel.excerpt.php) as 3 set-based UPDATE
 * statements, with no per-row loop, so the cost does not grow with a PHP loop
 * over customers. It logs how many services expired per type.
 *
 * My part: sole developer (384 of 385 commits).
 */

namespace App\Console\Commands;

use App\CustomerPackage;
use App\Enums\CustomerPackageStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckCustomerPackageExpiration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'check:customerpackageexpiration';

    /**
     * The console command description.
     */
    protected $description = 'Run this command to check for expired customer package.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $salonPackageCount = CustomerPackage::where('salon_package_status', CustomerPackageStatus::ACTIVE)->where('salon_package_end', '<', Carbon::now())->update(['salon_package_status' => CustomerPackageStatus::EXPIRED]);
        $gymPackageCount = CustomerPackage::where('gym_package_status', CustomerPackageStatus::ACTIVE)->where('gym_package_end', '<', Carbon::now())->update(['gym_package_status' => CustomerPackageStatus::EXPIRED]);
        $spaPackageCount = CustomerPackage::where('spa_package_status', CustomerPackageStatus::ACTIVE)->where('spa_package_end', '<', Carbon::now())->update(['spa_package_status' => CustomerPackageStatus::EXPIRED]);

        Log::notice("Today expired services are salon - {$salonPackageCount}, gym - {$gymPackageCount}, spa - {$spaPackageCount}");
    }
}
