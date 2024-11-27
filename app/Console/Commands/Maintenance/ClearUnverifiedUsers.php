<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class ClearUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:clearUnverifiedUsers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes users are older than 7 days and haven\'t verified their email';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        User::whereNull('email_verified_at')
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->delete();
    }
}
