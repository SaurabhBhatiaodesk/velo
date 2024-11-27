<?php

namespace App\Console\Commands\Addresses;

use Illuminate\Console\Command;
use App\Models\Address;
use App\Models\AddressTranslation;
use App\Models\Customer;
use App\Models\Locale;
use App\Models\Delivery;
use App\Models\Order;
use App\Repositories\AddressesRepository;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use DB;
use Log;

class Migration1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:addresses:migration1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replace duplicated addresses';

    private $english = null;
    private $hebrew = null;
    private $deleteCount = 0;
    private $addressesRepository = null;

    public function __construct(AddressesRepository $addressesRepository)
    {
        parent::__construct();
        $this->addressesRepository = $addressesRepository;
    }


    private function increaseDeleteCount($add = 1)
    {
        $this->deleteCount += $add;
        $this->info('delete count: ' . $this->deleteCount);
    }

    /**
     * Replaces all occurences of bad addresses with a correct address
     *
     * @var string
     */
    public function replaceBadAddresses($addresses)
    {
        $res = [];
        foreach ($addresses as $correctAddressId => $badAddressIds) {
            $correctAddress = Address::find($correctAddressId);

            $checkNulls = true;
            foreach ($badAddressIds as $badAddressId) {
                $badAddress = Address::find($badAddressId);
                // if the bad address is already deleted
                if (!$badAddress) {
                    continue;
                }

                // if the correctAddress is already deleted
                if (!$correctAddress) {
                    // replace it with the bad address anc continue
                    $correctAddressId = $badAddressId;
                    $correctAddress = $badAddress;
                    // and move on to the next bad address
                    continue;
                }

                // fill missing fields
                if ($checkNulls) {
                    $save = false;
                    $checkNulls = false;
                    foreach ($correctAddress->getAttributes() as $key => $value) {
                        if (
                            is_null($correctAddress->{$key}) ||
                            !strlen($correctAddress->{$key})
                        ) {
                            switch ($key) {
                                case 'slug':
                                case 'addressable_id':
                                case 'addressable_slug':
                                case 'translation_of':
                                    if (
                                        !is_null($badAddress->{$key}) &&
                                        strlen($badAddress->{$key})
                                    ) {
                                        $correctAddress->{$key} = $badAddress->{$key};
                                        $save = true;
                                    }
                                    break;
                                default:
                                    // all nulls filled
                                    $checkNulls = true;
                            }
                        }
                    }
                    if ($save) {
                        // if changes were made
                        $correctAddress->save();
                    }
                }

                // go over orders to find where the address is used
                if ($badAddress) {
                    foreach (['pickup', 'shipping', 'billing'] as $i => $addressType) {
                        if ($i === 0) {
                            $ids = Order::where($addressType . '_address_id', $badAddressId);
                        } else if (!is_null($badAddress->translation_of)) {
                            $ids->orWhere($addressType . '_address_id', $badAddress->translation_of);
                        }
                    }

                    $ids = $ids->pluck('id')->toArray();

                    Order::whereIn('id', $ids)->update([$addressType . '_address_id' => $correctAddressId]);
                    Delivery::whereIn('order_id', $ids)->update([$addressType . '_address' => $correctAddress]);

                    Address::withoutEvents(function () use ($badAddress, $correctAddressId) {
                        Address::where('translation_of', $badAddress->id)->update(['translation_of' => $correctAddressId]);
                        if (!$badAddress->delete()) {
                            $this->error('delete failed: ' . $badAddress->id);
                        } else {
                            $this->increaseDeleteCount();
                        }
                    });
                }
            }
        }
        return $res;
    }

    private function findDuplicateRootAddresses()
    {
        Address::chunk(5000, function ($addresses) {
            foreach ($addresses as $address) {
                $duplicates = Address::where('id', '!=', $address->id)
                    ->where('first_name', $address->first_name)
                    ->where('last_name', $address->last_name)
                    ->where('phone', $address->phone)
                    ->where('line1', $address->line1)
                    ->where('city', $address->city)
                    ->where('state', $address->state)
                    ->where('country', $address->country)
                    ->where('addressable_type', $address->addressable_type)
                    ->where('addressable_id', $address->addressable_id)
                    ->where('addressable_slug', $address->addressable_slug)
                    ->pluck('id');

                if ($duplicates->count()) {
                    $this->replaceBadAddresses([$address->id => $duplicates->toArray()]);
                }
            }
        });
    }

    private function findDuplicateAddressTranslations()
    {
        $this->line('start finding duplicate translations');
        Address::where('locale_id', '!=', $this->english->id)->chunk(5000, function ($addresses) {
            foreach ($addresses as $address) {
                // if the address isn't already deleted
                if ($address = Address::find($address->id)) {
                    // find duplicate translations
                    $duplicateTranslations = Address::where('id', '!=', $address->id)
                        ->where('locale_id', $address->locale_id)
                        ->where('translation_of', $address->translation_of)
                        ->pluck('id');

                    // if there are duplicate translations
                    if ($duplicateTranslations->count()) {
                        // replace them with the correct translation
                        $this->replaceBadAddresses([$address->id => $duplicateTranslations->toArray()]);
                    }
                }
            }
        });
        $this->line('done finding duplicate translations');
    }

    private function findOrphanedTranslations()
    {
        $this->line('finding orphaned translations');
        $unresolvedOrphans = [];
        Address::where('locale_id', '!=', $this->english->id)->chunk(5000, function ($addresses) {
            foreach ($addresses as $address) {
                // If the address is an orphan or already deleted
                if (
                    is_null($address->translation_of) ||
                    !$address::find($address->translation_of)
                ) {
                    // get orders assigned to the address
                    $addressOrders = Order::where('pickup_address_id', $address->id)
                        ->orWhere('billing_address_id', $address->id)
                        ->orWhere('shipping_address_id', $address->id)
                        ->get();

                    // if the address is assigned to orders
                    if ($addressOrders->count()) {
                        // try finding a twin (skip save if no twin is found)
                        $twin = $this->addressesRepository->get($address, $address->locale, true);
                        if ($twin) {
                            // if a twin is found, replace the address with the twin
                            $this->replaceBadAddresses([$twin->id => [$address->id]]);
                        } else {
                            // if no twin is found, add the address to the unresolved orphans
                            $unresolvedOrphans[] = $address;
                        }
                    } else {
                        // if the address is not assigned to any orders, delete it
                        $this->increaseDeleteCount($address->delete());
                    }
                }
            }
        });

        Log::info('unresolved orphans count: ' . count($unresolvedOrphans), $unresolvedOrphans);
        $this->line('done finding orphaned translations');
    }

    private function findDuplicateCustomers()
    {
        $this->line('Starting on duplicate customers');
        foreach (Customer::all() as $customer) {
            // already deleted
            if (!$customer = Customer::find($customer->id)) {
                continue;
            }

            $duplicates = Customer::where('id', '!=', $customer->id)
                ->where('store_slug', $customer->store_slug)
                ->where(function ($query) use ($customer) {
                    $query->where('first_name', $customer->first_name);
                    $query->orWhere('first_name', $customer->first_name . ' ');
                    $query->orWhere('first_name', ' ' . $customer->first_name);
                })
                ->where(function ($query) use ($customer) {
                    $query->where('last_name', $customer->last_name);
                    $query->orWhere('last_name', $customer->last_name . ' ');
                    $query->orWhere('last_name', ' ' . $customer->last_name);
                })
                ->get();

            if ($duplicates->count()) {
                $updateCustomer = false;
                foreach ($duplicates as $duplicate) {
                    if (
                        (is_null($customer->email) || !strlen($customer->email)) &&
                        (!is_null($duplicate->email) && strlen($duplicate->email))
                    ) {
                        $customer->email = $duplicate->email;
                        $updateCustomer = true;
                    }

                    if (
                        (is_null($customer->phone) || !strlen($customer->phone)) &&
                        (!is_null($duplicate->phone) && strlen($duplicate->phone))
                    ) {
                        $customer->phone = $duplicate->phone;
                        $updateCustomer = true;
                    }

                    $duplicate->notes()->update([
                        'notable_id' => $customer->id,
                    ]);

                    $duplicate->addresses()->update([
                        'addressable_id' => $customer->id,
                    ]);

                    $duplicate->orders()->update([
                        'customer_id' => $customer->id,
                    ]);

                    if (!$duplicate->delete()) {
                        $this->comment('customer delete failed: ' . $duplicate->id);
                    }
                }
                if ($updateCustomer) {
                    $customer->save();
                }
            }
        }
        $this->line('finished on duplicate customers');
    }

    public function breakLine1toStreetAndNumber()
    {
        $this->line('Starting on breaking line1 to street and number');
        Address::chunk(5000, function ($addresses) {
            $this->comment('Starting chunk on breaking line1 to street and number');
            foreach ($addresses as $address) {
                $this->comment('breaking address ' . $address->id);
                preg_match_all('(\d[\d.]*)', $address->line1, $matches);
                if (isset($matches[0][0])) {
                    $address->update([
                        'street' => trim(str_replace($matches[0][0], '', $address->line1)),
                        'number' => intVal($matches[0][0]),
                    ]);
                } else {
                    $address->update([
                        'street' => $address->line1,
                        'number' => 0
                    ]);
                }
            }
            $this->comment('Finished chunk on breaking line1 to street and number');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('line1');
        });
        $this->line('Done breaking line1 to street and number');
    }

    public function moveAddressTranslationsToOwnTable()
    {
        Address::where('locale_id', $this->hebrew->id)->chunk(5000, function ($addresses) {
            foreach ($addresses as $address) {
                if (is_null($address->street) || is_null($address->number)) {
                    $this->comment('breaking address ' . $address->id);
                    preg_match_all('(\d[\d.]*)', $address->line1, $matches);
                    if (isset($matches[0][0])) {
                        $address->update([
                            'street' => trim(str_replace($matches[0][0], '', $address->line1)),
                            'number' => intVal($matches[0][0]),
                        ]);
                    } else {
                        $address->update([
                            'street' => $address->line1,
                            'number' => 0
                        ]);
                    }
                }

                $newTranslation = new AddressTranslation([
                    'street' => $address->street,
                    'number' => $address->number,
                    'line2' => strlen($address->line2) ? $address->line2 : null,
                    'city' => $address->city,
                    'state' => $address->state,
                    'country' => $address->country,
                    'address_id' => $address->translation_of,
                ]);
                $newTranslation->setTable('addresses_' . $this->hebrew->iso);
                $newTranslation->save();

                Address::withoutEvents(function () use ($newTranslation) {
                    $this->increaseDeleteCount(Address::where('translation_of', $newTranslation->address_id)->delete());
                });
            }
        });
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('translation_of');
        });
    }

    public function addHebrewAddressesTable()
    {
        if (!Schema::hasTable('addresses_he')) {
            Schema::create('addresses_he', function (Blueprint $table) {
                $table->id();
                $table->string('street');
                $table->string('number');
                $table->string('line2')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('country');
                $table->foreignId('address_id');
                $table->timestamps();
            });
        }
    }

    public function modifyAddressesTable()
    {
        if (!Schema::hasColumn('addresses', 'street')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('street')->nullable()->after('line1');
                $table->string('number')->nullable()->after('street');
            });
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // make a patch slug to ensure this doesn't run twice
        $patchSlug = 'address_migration_1';
        if (!DB::table('data_patches')->where('slug', $patchSlug)->count()) {
            $this->english = Locale::where('iso', 'en_US')->first();
            $this->hebrew = Locale::where('iso', 'he')->first();

            /**
             * step 1 - add new database scaffolding
             */
            // add street and number columns to replace the line1 column
            // doing this first allow backwards compatibility for shipping codes check
            $this->modifyAddressesTable();
            // add a new table for hebrew addresses
            $this->addHebrewAddressesTable();

            /**
             * step 2 - handle duplications
             */
            // finds and unifies duplicated customers
            $this->findDuplicateCustomers();
            // finds and fixes translations with no parent address
            $this->findOrphanedTranslations();
            // finds and replaces duplicate address translations
            $this->findDuplicateAddressTranslations();
            // finds and replaces duplicate root addresses
            $this->findDuplicateRootAddresses();

            /**
             * step 3 - modify data structure
             */
            // adjust addresses to the new column structure
            $this->breakLine1toStreetAndNumber();

            /**
             * step 4 - seperate translations from root addresses
             */
            // move hebrew translations to their own table
            $this->moveAddressTranslationsToOwnTable();

            $this->comment('total deleted: ' . $this->deleteCount);

            DB::table('data_patches')->insert(['slug' => $patchSlug]);
        } else {
            $this->comment('patch already ran');
        }
    }
}
