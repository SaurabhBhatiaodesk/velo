<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Locale;
use App\Repositories\AddressesRepository;
use App\Http\Requests\Models\Addresses\StoreRequest;
use App\Http\Requests\Models\Addresses\UpdateRequest;
use App\Http\Requests\Models\Addresses\DeleteRequest;
use App\Http\Requests\Models\Addresses\TogglePickupRequest;
use Carbon\Carbon;

class AddressesController extends Controller
{
    private $addressesRepo;

    public function __construct(AddressesRepository $addressesRepository)
    {
        $this->addressesRepo = $addressesRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->respond(
            $this->addressesRepo->getMany(
                Address::where('user_id', auth()->id())->get(),
                auth()->user()->locale
            )
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function forStore(Store $store)
    {
        return $this->respond(
            $this->addressesRepo->getMany(
                $store->addresses,
                auth()->user()->locale
            )
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function forCustomer(Store $store, Customer $customer)
    {
        return $this->respond(
            $this->addressesRepo->getMany(
                $customer->addresses,
                auth()->user()->locale
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $address = $this->addressesRepo->get($request->all());
        if (!$address instanceof Address) {
            return $this->fail($address);
        }
        return $this->respond($address, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function show(Address $address)
    {
        return $this->respond($this->addressesRepo->get($address, auth()->user()->locale));
    }

    /**
     * Set address as default billing address
     *
     * @param  \App\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function setBilling(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $address = Address::find($inputs['id']);
        if (!$address) {
            return $this->respond(['error' => 'address.notFound'], 404);
        }
        try {
            $address->addressable->addresses()->update(['is_billing' => false]);
        } catch (\Exception $e) {
            // log the error
            \Log::error('AddressesController:setBilling (set is_billing to false) failed: ' . $e->getMessage());
            // return the error
            return $this->respond(['error' => 'address.saveFailed'], 500);
        }

        try {
            $address->update(['is_billing' => true]);
        } catch (\Exception $e) {
            // log the error
            \Log::error('AddressesController:setBilling (set new billing address) failed: ' . $e->getMessage());
            // return the error
            return $this->respond(['error' => 'address.saveFailed'], 500);
        }

        return $this->respond(['newBillingAddressId' => $address->id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Address $address)
    {
        // parse the request data for saving
        $addressData = $this->addressesRepo->prepareDataForSave($request->all());

        // if the street or city has changed, delete the translations
        $deleteTranslations = ($addressData['street'] !== $address->street || $addressData['city'] !== $address->city);

        // save the address
        if (!$address->update($addressData)) {
            return $this->respond(['error' => 'address.saveFailed'], 500);
        }

        // delete the translations if the street or city has changed
        if ($deleteTranslations) {
            $address->deleteTranslations();
        }

        return $this->respond($address);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Address $address)
    {
        // translations are deleted via the Address model's deleting boot method
        if ($address->slug) {
            return $this->respond(['error' => 'address.deleteFailed.slug'], 403);
        }

        $address->deleteTranslations();

        if (!$address->delete()) {
            return $this->respond([
                'error' => 'deleteFailed'
            ], 500);
        }

        return $this->respond();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function togglePickup(TogglePickupRequest $request, Store $store, Address $address)
    {
        $pickupAddressesCount = $store->pickup_addresses()->count();
        $isPickup = $request->input('is_pickup');
        if (!$address->update(['is_pickup' => $isPickup])) {
            return $this->respond(['error' => 'address.saveFailed'], 500);
        }

        $limit = config('plans.' . $store->plan_subscription->subscribable->name . '.address.limit');
        if (
            ($pickupAddressesCount > 0 && !config('plans.' . $store->plan_subscription->subscribable->name . '.address.can_add')) ||
            ($limit > 0 && $pickupAddressesCount >= $limit)
        ) {
            return $this->respond(['message' => 'address.limitReached'], 200);
        }

        if ($pickupAddressesCount >= config('plans.' . $store->plan_subscription->subscribable->name . '.address.included')) {
            \Log::info('Store ' . $store->slug . ' has reached the included pickup addresses limit', [
                'pickupAddressesCount' => $pickupAddressesCount,
                'is_pickup' => $isPickup,
            ]);
            if ($isPickup && $pickupAddressesCount > 0) {
                $subscription = $store->subscriptions()->create([
                    'store_slug' => $store->slug,
                    'starts_at' => now(),
                    // synchronize to end of plan subscription to synchoronize with the store's billing cycle
                    'ends_at' => $store->plan_subscription->ends_at,
                    // auto_renew is false because we need to count subscriptions by type when renewing the plan
                    'auto_renew' => false,
                    'renewed_from' => null,
                    'subscribable_type' => Address::class,
                    'subscribable_id' => $address->id,
                ]);

                $priceFactor = $store->plan_subscription->ends_at->diffInDays(Carbon::now()) / $store->plan_subscription->ends_at->diffInDays($store->plan_subscription->starts_at);
                $chargeResult = $store->billingRepo()->billAndChargeSubscription($subscription, 'address', $priceFactor);
                if (isset($chargeResult['fail'])) {
                    if ($subscription->bill) {
                        $subscription->bill->delete();
                    }
                    $subscription->delete();
                    $address->update(['is_pickup' => false]);
                    return $this->fail($chargeResult);
                }
            }
        }

        return $this->respond($address);
    }
}
