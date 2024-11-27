<?php

namespace App\Console\Commands\Deliveries;

use Illuminate\Console\Command;
use App\Models\Order;

class TransmitMissingDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:transmitMissingDocuments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transmit commercial invoices where needed';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $orders = Order::whereHas('delivery', function ($query) {
            $query->whereNull('commercial_invoice_transmitted_at');
            $query->whereHas('polygon.shipping_code', function ($query) {
                $query->where('is_international', true);
            });
            $query->where(function ($query) {
                $query->where('status', 'accepted');
                $query->orWhere('status', 'placed');
            });
        })
            ->get();

        foreach ($orders as $order) {
            if (
                !is_null($order->getCommercialInvoice()) &&
                !is_null($order->delivery->remote_id) &&
                strlen($order->delivery->remote_id)
            ) {
                $repo = $order->delivery->polygon->courier->getRepo();
                $repo->transmitDocuments($order);
            }
        }
    }
}
