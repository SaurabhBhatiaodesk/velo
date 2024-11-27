<?php

namespace App\Console\Commands\Couriers;

use Illuminate\Console\Command;
use App\Models\Courier;
use Log;

class RefreshStations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:couriers:refreshStations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refreshes the list of stations for all couriers.';

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
                if (method_exists($repo, 'getStations')) {
                    $stations = $repo->getStations(true);
                    if (isset($stations['fail'])) {
                        Log::error('Failed to refresh stations for courier ' . $courier->name, $stations);
                    }
                }
            }
        }
    }
}
