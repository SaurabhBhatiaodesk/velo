<?php

namespace App\Imports;

use App\Models\Customer;
use App\Repositories\OrderCreateRepository;
use Illuminate\Support\Collection;
use App\Events\Models\Order\Saved as OrderSaved;
use App\Events\Models\Order\ImportComplete as OrdersImportComplete;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\ImportFailed;

use Log;

class OrdersImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsOnError, SkipsEmptyRows, WithChunkReading, ShouldQueue, WithEvents
{
    use SkipsFailures, SkipsErrors, Importable;

    private $store;
    private $orders;
    private $fails;

    public function __construct($store)
    {
        $this->store = $store;
        $this->orders = new \Illuminate\Database\Eloquent\Collection();
    }

    public function chunkSize(): int
    {
        return 10;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function collection(Collection $rows)
    {
        $repo = new OrderCreateRepository;

        $storeAddress = $this->store->getBillingAddress()->toArray();
        foreach ($rows as $i => $row) {
            $customerData = [
                'first_name' => $row['customer_first_name'],
                'last_name' => $row['customer_last_name'],
                'phone' => $row['customer_phone'],
                'email' => (strlen($row['customer_email'])) ? $row['customer_email'] : null,
                'store_slug' => $this->store->slug,
            ];

            if (!str_starts_with($customerData['phone'], '0')) {
                $customerData['phone'] = '0' . $customerData['phone'];
            }

            $customer = $this->store->customers()->where('phone', $customerData['phone'])->first();
            if (
                !$customer || (
                    $customer->first_name !== $customer['first_name'] &&
                    $customer->last_name !== $customer['last_name']
                )
            ) {
                $customer = Customer::create($customerData);
            } else {
                $customer->update($customerData);
            }

            $customerAddress = [
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'street' => $row['customer_street'],
                'number' => $row['customer_house_number'],
                'line2' => $row['customer_address_details'],
                'city' => $row['customer_city'],
                'state' => $row['customer_state'],
                'country' => $row['customer_counrty'],
                'addressable_type' => 'App\\Models\\Customer',
                'addressable_id' => $customer->id,
            ];

            $order = $repo->save($repo->prepareRequest([
                'external_id' => $row['order_id'],
                'store_slug' => $this->store->slug,
                'store' => $this->store,
                'weight' => strlen($row['weight']) ? $row['weight'] : config('measurments.smBag.' . (($this->store->imperial_units) ? 'imperial' : 'metric') . '.weight'),
                'dimensions' => config('measurments.smBag.' . (($this->store->imperial_units) ? 'imperial' : 'metric') . '.dimensions'),
                'customer' => $customer->toArray(),
                'customerAddress' => $customerAddress,
                'storeAddress' => $storeAddress,
                'note' => strlen($row['order_note']) ? $row['order_note'] : 0,
            ]), false);

            if (isset($order['fail'])) {
                $this->fails[] = $order;
            } else {
                OrderSaved::dispatch($order);
                $this->orders->push($order);
            }
        }
    }

    public function rules(): array
    {
        return [
            'customer_first_name' => ['required'],
            'customer_last_name' => ['required'],
            'customer_phone' => ['required'],
            'customer_street' => ['required'],
            'customer_house_number' => ['required'],
            'customer_city' => ['required'],
            'customer_counrty' => ['required'],
        ];
    }

    public function onError(\Throwable $e)
    {
        Log::info('e', [$e]);
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function getFails()
    {
        if (is_null($this->fails) || !count($this->fails)) {
            return [];
        }
        return $this->fails;
    }

    public function getResults()
    {
        return [
            'fails' => $this->getFails(),
            'orders' => $this->getOrders(),
        ];
    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                $exception = $event->getException();
                Log::info('OrdersImport ImportFailed: ' . $exception->getMessage(), $exception->getTrace());
            },

            AfterImport::class => function (AfterImport $event) {
                OrdersImportComplete::dispatch($this->store);
            },
        ];
    }
}
