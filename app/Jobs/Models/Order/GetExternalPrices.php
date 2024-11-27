<?php

namespace App\Jobs\Models\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Events\Order\QuoteReceived as OrderQuoteReceived;
use Log;

class GetExternalPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The relevant polygons
     *
     * @var collection \App\Models\Polygon
     */
    public $polygons;

    /**
     * Create a new job instance.
     *
     * @param  App\Models\Order  $order
     * @return void
     */
    public function __construct(Order $order, $polygons)
    {
        $this->order = $order;
        $this->polygons = $polygons;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        QuoteReceived::dispatch($this->order->name, $quote);
        return true;
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed(Throwable $e)
    {
        Log::info('failed  to get quote: ' . $e->getMessage());
    }
}
