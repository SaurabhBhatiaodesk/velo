<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Repositories\SubscriptionsRepository;

class BillSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge stores for their subscriptions - runs daily';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $repo = new SubscriptionsRepository();
        $repo->autoRenew();
    }
}
