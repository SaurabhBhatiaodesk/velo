<?php

namespace App\Jobs\Models\Delivery;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Delivery;

class RenewPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The delivery instance.
     *
     * @var \App\Models\Order
     */
    public $delivery;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->delivery->has_push) {
            $this->delivery->update(['has_push' => false]);
        }
        switch ($this->delivery->status) {
            case 'accepted':
            case 'pending_pickup':
            case 'transit':
            case 'transit_to_destination':
            case 'transit_to_warehouse':
            case 'transit_to_sender':
            case 'in_warehouse':
                $repo = $this->delivery->polygon->courier->getRepo();
                if (method_exists($repo, 'pushSubscribe')) {
                    $repo->pushSubscribe($this->delivery->getOrder());
                }
        }
    }
}
