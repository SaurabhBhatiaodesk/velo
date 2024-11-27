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
        /*
         * Global maintenance functions
         * These only run on one server, but they are safe to run on all servers.
         * They handle things like db cleanup, billing, clearance and other global tasks.
         */
        // $schedule->command('auth:clear-resets')->everyFifteenMinutes()->withoutOverlapping();
        // $schedule->command('velo:fixDuplicatePlanSubscriptions')->dailyAt('8:00')->withoutOverlapping();
        // $schedule->command('velo:clearUnverifiedUsers')->dailyAt('4:10')->withoutOverlapping();
        // $schedule->command('velo:reportInvalidSlugs')->dailyAt('7:00')->withoutOverlapping();

        // $schedule->command('velo:repairPushSubscriptions')->everyThirtyMinutes()->withoutOverlapping();
        // $schedule->command('velo:batchTrackCouriers')->everyThirtyMinutes()->withoutOverlapping();
        // $schedule->command('velo:transmitMissingDocuments')->everyThirtyMinutes()->withoutOverlapping();

        // $schedule->command('velo:shopify:clearUnusedShopifyShops')->dailyAt('3:10')->withoutOverlapping();

        // $schedule->command('velo:support:sendOverdueDeliveriesReport')->dailyAt('7:30')->withoutOverlapping();

        // $schedule->command('velo:billing:cancelled')->dailyAt('8:10')->withoutOverlapping();
        // $schedule->command('velo:billing:subscriptions')->dailyAt('8:40')->withoutOverlapping();
        // $schedule->command('velo:billing:chargePending')->dailyAt('9:10')->withoutOverlapping();
        // $schedule->command('velo:billing:billPending')->dailyAt('9:40')->withoutOverlapping();
        // $schedule->command('velo:billing:generateMissingInvoices')->dailyAt('5:00')->withoutOverlapping();

        // $schedule->command('velo:billing:updateDeliveryPrices')->dailyAt('18:10')->withoutOverlapping();
        // $schedule->command('velo:billing:chargeDeliveries')->dailyAt('23:30')->withoutOverlapping();
        // $schedule->command('velo:billing:sendEnterpriseReportsAndInvoices')->monthly(); // 1st day of the month at 00:00
        // $schedule->command('velo:billing:sendOverdueBillsReport')->lastDayOfMonth('23:00')->withoutOverlapping();
        // $schedule->command('velo:archiveOrders')->monthlyOn(15, '00:00')->withoutOverlapping();

        // $schedule->command('velo:couriers:rebindGlobalWebhooks')->dailyAt('1:10')->withoutOverlapping();

        /*
         * Local maintenance functions
         * These run on all servers.
         * They handle things like refresh local caches, update local data and other local tasks.
         */
        $schedule->command('velo:couriers:refreshStations')->dailyAt('1:00')->withoutOverlapping();
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
