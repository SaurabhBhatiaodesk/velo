<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Notification;
use App\Models\Store;


class FixDuplicatePlanSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:fixDuplicatePlanSubscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disables auto-renew and credits uncredited charges on duplicated plan subscriptions';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $stores = Store::all();
        $res = [
            'skipped' => [],
            'errors' => [],
            'bills' => [],
        ];
        foreach ($stores as $store) {
            if ($store->plan_subscription()->count() > 1) {
                $subs = $store->plan_subscription()->get();
                $first = true;
                foreach ($subs as $sub) {
                    if (!$sub->bill || intVal($sub->bill->getTotalWithTax()) === 0) {
                        $res['skipped'][$store->slug][] = 'free ' . $sub->id;
                        continue;
                    }
                    if ($sub->bill && $sub->bill->credits()->count()) {
                        $res['skipped'][$store->slug][] = 'credited ' . $sub->id;
                        continue;
                    }
                    if ($first) {
                        $res['skipped'][$store->slug][] = 'first ' . $sub->id;
                        $first = false;
                        continue;
                    } else {
                        if (!$sub->update(['auto_renew' => false])) {
                            if (!isset($res['errors'][$store->slug])) {
                                $res['errors'][$store->slug] = [];
                            }
                            $res['errors'][$store->slug][] = $sub;
                        } else {
                            if (!isset($res['bills'][$store->slug])) {
                                $res['bills'][$store->slug] = [];
                            }
                            $res['bills'][$store->slug][$sub->bill->id] = $sub->bill->credit()->create([
                                'description' => 'Double charged ' . $sub->bill->description,
                                'total' => $sub->bill->getTotalWithTax(),
                                'currency_id' => $sub->bill->currency_id,
                                'store_slug' => $sub->bill->store_slug,
                            ]);
                        }
                    }
                }
            }
        }

        if (count($res['bills'])) {
            Mail::to('itay@veloapp.io')->send(new Notification([
                'message' => 'Credit Duplicate Plan Subcriptions',
                'result' => $res,
            ]));
        }
        return $res;
    }
}
