<?php

namespace App\Jobs\Integrations\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Repositories\OrderCreateRepository;
use App\Repositories\Integrations\Shopify\IntegrationRepository;
use App\Repositories\AddressesRepository;
use App\Models\Polygon;


class ImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderCreateRepo;
    public $shopifyRepo;

    public $shopifyOrder;
    public $shopifyShop;

    public $addressesRepo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shopifyOrder, $shopifyShop)
    {
        $this->orderCreateRepo = new OrderCreateRepository();
        $this->shopifyRepo = new IntegrationRepository();
        $this->addressesRepo = new AddressesRepository();

        $this->shopifyOrder = $this->shopifyRepo->prepareOrderData($shopifyOrder, $shopifyShop);
        $this->shopifyShop = $shopifyShop;
    }

    private function addMeasurements($orderData)
    {
        if (
            !isset($orderData['dimensions']) ||
            !isset($orderData['dimensions']['width']) ||
            !isset($orderData['dimensions']['height']) ||
            !isset($orderData['dimensions']['depth']) ||
            !isset($orderData['weight'])
        ) {
            $orderData['weight'] = config('measurments.smBag.' . (($this->shopifyShop->store->imperial_units) ? 'imperial' : 'metric') . '.weight');
            $orderData['dimensions'] = config('measurments.smBag.' . (($this->shopifyShop->store->imperial_units) ? 'imperial' : 'metric') . '.dimensions');
        }
        return $orderData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!isset($this->shopifyOrder['shipping_address']) || is_null($this->shopifyOrder['shipping_address'])) {
            return;
            // $results['failed'][$this->shopifyOrder['id']] = [
            //     'customer' => isset($this->shopifyOrder['customer']) ? $this->shopifyOrder['customer'] : [],
            //     'customerAddress' => isset($this->shopifyOrder['billing_address']) ? isset($this->shopifyOrder['billing_address']) : [],
            //     'external_id' => $this->shopifyOrder['name'],
            //     'shopify_id' => $this->shopifyOrder['id'],
            //     'store' => $this->shopifyShop->store,
            //     'storeAddress' => (isset($this->shopifyOrder['pickup_address']) && !is_null($this->shopifyOrder['pickup_address'])) ? $this->shopifyOrder['pickup_address'] : $this->storeAddress,
            // ];
        }

        if (
            isset($this->shopifyOrder['customer']) &&
            (
                !isset($this->shopifyOrder['customer']['phone']) ||
                is_null($this->shopifyOrder['customer']['phone']) ||
                !strlen($this->shopifyOrder['customer']['phone'])
            ) &&
            isset($this->shopifyOrder['shipping_address']['phone']) &&
            !is_null($this->shopifyOrder['shipping_address']['phone']) &&
            strlen($this->shopifyOrder['shipping_address']['phone'])
        ) {
            $this->shopifyOrder['customer']['phone'] = $this->shopifyOrder['shipping_address']['phone'];
        }

        $order = [
            'source' => 'shopify',
            'customer' => $this->shopifyOrder['customer'],
            'customer_id' => $this->shopifyOrder['customer']->id,
            'customerAddress' => $this->addressesRepo->get($this->shopifyOrder['shipping_address']),
            'deliveryType' => 'normal',
            'external_id' => $this->shopifyOrder['name'],
            'shopify_id' => $this->shopifyOrder['id'],
            'store' => $this->shopifyShop->store,
        ];

        if (isset($this->shopifyOrder['pickup_address'])) {
            $order['storeAddress'] = $this->shopifyOrder['pickup_address'];
        }

        $res = $this->orderCreateRepo->save($this->orderCreateRepo->prepareRequest($order));
    }
}
