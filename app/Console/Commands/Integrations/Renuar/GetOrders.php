<?php

namespace App\Console\Commands\Integrations\Renuar;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Error as AdminErrorEmail;
use App\Repositories\OrderCreateRepository;
use App\Repositories\AddressesRepository;
use App\Models\Order;
use App\Models\Store;
use App\Models\Polygon;
use DB;

class GetOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:renuar:getOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects to Renuar\'s remote DB and gets all the orders';

    /**
     * Execute the console command.
     *
     * Data example:
     * [
     *   {
     *     "Company": "Renuar",
     *     "Order_Date": date data type,
     *     "Order_Num": "400025489",
     *     "Order": "COR230076364",
     *     "City": "בת ים",
     *     "Street": "ז'בוטינסקי",
     *     "House_Num": "83",
     *     "Customer_Name": "משה",
     *     "Customer_Family_Name": "Levy",
     *     "Customer_Phone": "0544350811",
     *     "Customer_Email": "Nofarhatan3@gmail.com",
     *     "Date_Received": nullable datetime data type
     *   },
     *   {
     *     "Company": "TFS",
     *     "Order_Num": "500054872",
     *     "Order": "COT230077413",
     *     "City": "ראשל\"צ",
     *     "Street": "פיסקר",
     *     "House_Num": "72",
     *     "Customer_Name": "דיאנה",
     *     "Customer_Family_Name": "מזרחי",
     *     "Customer_Phone": "0526977136",
     *     "Customer_Email": "maayan21086@gmail.com",
     *     "Date_Received": null
     *   }
     * ]
     *
     * @return boolean
     */
    public function handle($attempt = 1)
    {
        // get the store
        $store = Store::where('slug', 'renuar')->first();

        // get the orders from the remote DB
        try {
            $renuarOrdersData = DB::connection('renuar')
                ->table('T_Velo_Orders')
                ->whereNull('Date_Received')
                ->get()
                ->toArray();

            DB::disconnect('renuar');
        } catch (\Exception $e) {
            // if the connection failed, try again (up to 5 times)
            if ($attempt < 6) {
                return $this->handle($attempt + 1);
            } else {
                // send an email to the admin
                Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                    'message' => 'Renuar GetOrders DB connection failed',
                    'error' => $e->getMessage(),
                ]));
                return false;
            }
        }

        // get the same-day polygon
        $sameDay = Polygon::whereHas('courier', function ($query) {
            $query->where('name', 'mahirli');
        })->whereHas('shipping_code', function ($query) {
            $query->where('code', 'VELOAPPIO_SAME_DAY');
        })->first();

        // get the next-day polygon
        $nextDay = Polygon::whereHas('courier', function ($query) {
            $query->where('name', 'mahirli');
        })->whereHas('shipping_code', function ($query) {
            $query->where('code', 'VELOAPPIO_NEXT_DAY');
        })->first();

        $orderCreateRepo = new OrderCreateRepository();
        $addressesRepo = new AddressesRepository();
        $orders = [];
        $errors = [];
        foreach ($renuarOrdersData as $i => $renuarOrderData) {
            rescue(function () use ($sameDay, $nextDay, $store, $orders, $errors, $orderCreateRepo, $addressesRepo, $renuarOrderData) {
                $existingOrder = Order::where('store_slug', $store->slug)
                    ->where('external_id', 'LIKE', $renuarOrderData->Order_Num . '%')
                    ->orWhere('external_id', 'LIKE', $renuarOrderData->Order . '%')
                    ->orWhere('name', 'LIKE', $renuarOrderData->Order_Num . '%')
                    ->orWhere('name', 'LIKE', $renuarOrderData->Order . '%')
                    ->first();

                // check if order has already been created
                if (!$existingOrder) {
                    $orderData = [
                        'source' => 'renuar',
                        'name' => $renuarOrderData->Order,
                        'external_id' => $renuarOrderData->Order_Num,
                        'storeAddress' => $store->addresses()->first(),
                        'store_slug' => $store->slug,
                        'user_id' => $store->user->id,
                        'products' => [],
                        'delivery' => [
                            'weight' => 0,
                            'dimensions' => [],
                            'pickup_address' => $store->addresses()->first()
                        ]
                    ];

                    $orderData['pickup_address_id'] = $orderData['delivery']['pickup_address']->id;


                    // find existing customer
                    $orderData['customer'] = ($existingOrder) ? $existingOrder->customer : $store->customers()
                        ->where('first_name', $renuarOrderData->Customer_Name)
                        ->where('last_name', $renuarOrderData->Customer_Family_Name)
                        ->where('phone', preg_replace('/[^0-9]/', '', $renuarOrderData->Customer_Phone))
                        ->first();

                    // create customer if not found
                    if (!$orderData['customer']) {
                        $orderData['customer'] = $store->customers()->create([
                            'first_name' => $renuarOrderData->Customer_Name,
                            'last_name' => $renuarOrderData->Customer_Family_Name,
                            'email' => $renuarOrderData->Customer_Email,
                            'phone' => $renuarOrderData->Customer_Phone,
                        ]);
                    }
                    $orderData['customer_id'] = $orderData['customer']->id;

                    if ($existingOrder) {
                        $orderData['id'] = $existingOrder->id;
                    }

                    // get customer address
                    $orderData['delivery']['shipping_address'] = ($existingOrder && $existingOrder->shipping_address) ? $existingOrder->shipping_address : $addressesRepo->get([
                        'first_name' => $renuarOrderData->Customer_Name,
                        'last_name' => $renuarOrderData->Customer_Family_Name,
                        'street' => $renuarOrderData->Street,
                        'number' => $renuarOrderData->House_Num,
                        'city' => $renuarOrderData->City,
                        'country' => 'Israel',
                        'phone' => $renuarOrderData->Customer_Phone,
                        'addressable_id' => $orderData['customer']->id,
                        'addressable_type' => 'App\\Models\\Customer',
                        'user_id' => $store->user->id,
                    ]);
                    $orderData['shipping_address_id'] = $orderData['delivery']['shipping_address']->id;

                    // set polygon
                    if ($sameDay->checkAddress($orderData['delivery']['shipping_address'], 'dropoff_')) {
                        $orderData['delivery']['polygon'] = $sameDay;
                    } else if ($nextDay->checkAddress($orderData['delivery']['shipping_address'], 'dropoff_')) {
                        $orderData['delivery']['polygon'] = $nextDay;
                    } else {
                        return;
                    }
                    $orderData['delivery']['polygon_id'] = $orderData['delivery']['polygon']->id;

                    // save the order
                    $orders[] = $orderCreateRepo->save($orderData);
                }
            });
        }

        // report any errors via email
        if (count($errors)) {
            Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                'message' => 'Renuar GetOrders function failed',
                'error' => $errors,
            ]));
        }

        return true;
    }
}
