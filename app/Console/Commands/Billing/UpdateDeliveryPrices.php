<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Models\Bill;

class UpdateDeliveryPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:updateDeliveryPrices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update delivery prices based on polygon conditions';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bills = Bill::whereNull('transaction_id')
            ->where('billable_type', 'App\\Models\\Delivery')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($bills as $bill) {
            if (is_null($bill->transaction_id)) {
                $cost = $bill->billable->getPrice(false);
                if (isset($cost['price'])) {
                    // set the locale to the store owner's locale
                    app()->setLocale($bill->store->user->locale->iso);

                    // check late fee threshold
                    if (
                        !$bill->store->enterprise_billing &&
                        !is_null($bill->billable->accepted_at) && // this is a redundancy - a billed delivery should always have an accepted_at date
                        !$bill->billable->accepted_at->isSameMonth(now())
                    ) {
                        // if the delivery has only one bill
                        if ($bill->billable->bills()->count() === 1) {
                            // create a new bill for the late fee
                            Bill::create([
                                'description' => __('billing.late_fee') . ' - ' . $bill->billable->getOrder()->name,
                                'billable_type' => 'App\\Models\\Delivery',
                                'billable_id' => $bill->billable_id,
                                'total' => round($cost['price'] * 0.05, 2),
                                'currency_id' => $bill->store->currency_id,
                                'store_slug' => $bill->store_slug,
                                'cost' => $bill->billable->getCost()->price,
                            ]);
                            // if the delivery has more than one bill
                        } else {
                            // iterate through the bills
                            foreach ($bill->billable->bills as $innerBill) {
                                // try to find the late fee bill
                                if ($innerBill->description === __('billing.late_fee') . ' - ' . $bill->billable->getOrder()->name) {
                                    // update the late fee bill's total
                                    $innerBill->update(['total' => round($cost['price'] * 0.05, 2)]);
                                }
                            }
                        }
                    }

                    // update bill price
                    if (
                        // bill is not paid
                        is_null($bill->transaction_id) &&
                        // bill's price is different from the calculated price
                        $cost['price'] !== $bill->total &&
                        // bill is for a delivery and not for a late fee
                        $bill->description !== __('billing.late_fee') . ' - ' . $bill->billable->getOrder()->name
                    ) {
                        $bill->update(['total' => $cost['price']]);
                    }
                }
            }
        }
    }
}
