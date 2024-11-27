<?php

namespace App\Console\Commands\Integrations\Renuar;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Error as AdminErrorEmail;
use App\Models\Order;
use App\Repositories\DeliveriesRepository;
use Carbon\Carbon;
use DB;

class MarkDeliveredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:renuar:markDeliveredOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects to Renuar\'s remote DB and set delivery time on all delivered orders';

    /**
     * Execute the console command.
     *
     * Data example:
     * [
     *   {
     *     "Company": "Renuar",
     *     "Order_Num": "400025489",
     *     "Order": "COR230076364",
     *     "City": "בת ים",
     *     "Street": "ז'בוטינסקי",
     *     "House_Num": "83",
     *     "Customer_Name": "משה",
     *     "Customer_Family_Name": "Levy",
     *     "Customer_Phone": "0544350811",
     *     "Customer_Email": "Nofarhatan3@gmail.com",
     *     "Date_Received": null
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
     * @return mixed
     */
    public function handle($attempt = 1)
    {
        try {
            $renuarOrdersData = DB::connection('renuar')->select('SELECT * FROM T_Velo_Orders WHERE Date_Received IS NULL');
        } catch (\Exception $e) {
            if ($attempt < 6) {
                return $this->handle($attempt + 1);
            }
            Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                'message' => 'Renuar MarkDeliveredOrders DB select failed',
                'error' => $e->getMessage(),
            ]));
            return false;
        }

        $ordersQuery = Order::whereIn('external_id', array_map(fn($order) => $order->Order_Num, $renuarOrdersData))
            ->orWhereIn('name', array_map(fn($order) => $order->Order, $renuarOrdersData))
            ->with('delivery')
            ->whereHas('delivery', function ($query) {
                $query->whereNotNull('delivered_at');
            });

        try {
            $deliveriesRepo = new DeliveriesRepository();
            $deliveriesRepo->trackMany($ordersQuery->get());
        } catch (\Exception $e) {
            Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                'message' => 'Renuar MarkDeliveredOrders tracking deliveries failed',
                'error' => $e->getMessage(),
            ]));
        }

        foreach ($ordersQuery->get() as $order) {
            if (!is_null($order->delivery->delivered_at)) {
                try {
                    DB::connection('renuar')
                        ->table('T_Velo_Orders')
                        ->where('Order_Num', $order->external_id)
                        ->update([
                            'Date_Received' => $order->delivery->delivered_at->toDateTimeString(),
                        ]);
                } catch (\Exception $e) {
                    Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                        'message' => 'Renuar MarkDeliveredOrders DB select failed',
                        'Order_Num' => $order->external_id,
                        'accepted_at' => $order->accepted_at,
                        'delivered_at' => $order->delivered_at,
                        'error' => $e->getMessage(),
                    ]));
                }
            } else if (!is_null($order->delivery->accepted_at) && $order->delivery->accepted_at->isBefore(Carbon::now()->subDays(14))) {
                try {
                    DB::connection('renuar')
                        ->table('T_Velo_Orders')
                        ->where('Order_Num', $order->external_id)
                        ->update([
                            'Date_Received' => Carbon::now()->toDateTimeString()
                        ]);
                } catch (\Exception $e) {
                    Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                        'message' => 'Renuar MarkDeliveredOrders DB select failed',
                        'Order_Num' => $order->external_id,
                        'accepted_at' => $order->accepted_at,
                        'delivered_at' => $order->delivered_at,
                        'error' => $e->getMessage(),
                    ]));
                }
            }
        }
        DB::disconnect('renuar');
    }
}
