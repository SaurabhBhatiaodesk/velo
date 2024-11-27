<?php

namespace App\Console\Commands\Addresses;

use Illuminate\Console\Command;
use App\Models\Address;
use App\Models\Store;
use Log;

class DuplicatesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:addresses:duplicatesReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a report of suspected duplicate addresses';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $addresses = Address::where('addressable_type', 'App\\Models\\Store')->get();
        $checked = [];
        $duplicates = [];
        $stores = [];
        foreach ($addresses as $address1) {
            $street1 = str_ends_with($address1->street, ' Street') ? substr($address1->street, 0, -7) : $address1->street;
            $street1 = str_ends_with($address1->street, ' St') ? substr($address1->street, 0, -3) : $address1->street;
            foreach ($addresses as $address2) {
                $street2 = str_ends_with($address2->street, ' Street') ? substr($address2->street, 0, -7) : $address2->street;
                $street2 = str_ends_with($address2->street, ' St') ? substr($address2->street, 0, -3) : $address2->street;
                if (
                    !isset($checked[$address2->id]) &&
                    $address1->id !== $address2->id &&
                    $address1->street === $address2->street &&
                    $address1->number === $address2->number &&
                    $address1->city === $address2->city
                ) {
                    if (!isset($duplicates[$address1->id])) {
                        $duplicates[$address1->id] = [];
                    }
                    $duplicates[$address1->id][] = $address2->id;
                    $checked[$address2->id] = true;
                    if (!isset($stores[$address1->addressable_slug])) {
                        if (!$address1->addressable) {
                            Log::debug('Address without addressable', [$address1->toArray()]);
                        } else {
                            $stores[$address1->addressable_slug] = $address1->addressable->addresses()->count();
                        }
                    }
                }
            }
            $checked[$address1->id] = true;
        }
        $cleanCommand = '$this->replaceBadAddresses([' . PHP_EOL;
        foreach ($duplicates as $correct => $incorrects) {
            $cleanCommand .= $correct . ' => [';
            foreach ($incorrects as $incorrect) {
                $cleanCommand .= $incorrect . ',';
            }
            $cleanCommand = rtrim($cleanCommand, ',') . '],' . PHP_EOL;
        }
        $cleanCommand .= ']);';
        if (count($duplicates)) {
            Log::info($cleanCommand);
        } else {
            $stores = PHP_EOL;
            foreach (Store::all() as $store) {
                $count = $store->addresses()->count();
                if ($count > 1) {
                    $stores .= $store->slug . PHP_EOL;
                    foreach ($store->addresses as $address) {
                        $stores .= $address->street . ' ' . $address->number . ', ';
                        if (!is_null($address->line2) && strlen($address->line2)) {
                            $stores .= $address->line2 . ', ';
                        }
                        $stores .= $address->city . PHP_EOL;
                    }
                    $stores .= PHP_EOL;
                }
            }
            Log::info($stores);
        }
    }
}
