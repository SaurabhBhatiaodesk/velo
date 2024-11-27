<?php

namespace App\Console\Commands\Couriers;

use Illuminate\Console\Command;
use App\Models\Courier;
use Log;

class RebindGlobalWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:couriers:rebindGlobalWebhooks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bind global webhooks for couriers where necessary.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach (Courier::all() as $courier) {
            if ($courier->polygons()->where('active', true)->exists()) {
                $repo = $courier->getRepo();
                if (method_exists($repo, 'bindWebhook')) {
                    $stations = $repo->bindWebhook();
                    if (isset($stations['fail'])) {
                        Log::error('Failed to rebind global webhook for courier ' . $courier->name);
                    }
                }
            }
        }
    }
}
