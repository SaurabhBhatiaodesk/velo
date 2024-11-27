<?php

namespace App\Repositories;

use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\Facades\DNS1DFacade as DNS1D;
use Storage;
use App\Traits\SavesFiles;
use App\Repositories\StringFormattingRepository;
use App\Repositories\AddressesRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Testing\MimeType;

class StickersRepository extends BaseRepository
{
    use SavesFiles;
    public $pageWidth = 283.8;
    public $pageHeight = 283.8;

    private function printAddressName($address)
    {
        return $address->first_name . ' ' . $address->last_name;
    }

    public function getStickerHtml($order)
    {
        $sticker = $this->getStickerHeader($order, true);
        $sticker .= $this->getStickerBody($order, true);
        $sticker .= $this->getStickerFooter(true);
        return $sticker;
    }

    public function getReturnReplaceUrl($order)
    {
        return route('endClient.trackingPage', [
            'slug' => Crypt::encrypt($order->shipping_address->phone . '/' . $order->name),
        ]);
    }

    public function getStickerBody($order, $returnHtml = false)
    {
        if (!$order->delivery->shipping_address) {
            return $this->fail('order.noShippingAddress');
        }
        if (!$order->delivery->pickup_address) {
            return $this->fail('order.noPickupAddress');
        }
        $delivery = $order->deliveries()->latest('created_at')->first();
        if (!$delivery) {
            return $this->fail('order.noDelivery');
        }

        $locale = $order->delivery->polygon->courier->locale;
        app()->setLocale($locale->iso);
        $addressesRepo = new AddressesRepository();
        $pickupAddress = $addressesRepo->get($order->delivery->pickup_address, $locale);
        $shippingAddress = $addressesRepo->get($order->delivery->shipping_address, $locale);

        $sender = [
            'name' => ($pickupAddress->addressable_type === 'App\\Models\\Store') ? $order->store->name : $this->printAddressName($pickupAddress),
            'phone' => str_replace('+972', '0', $pickupAddress->phone),
            'street' => $pickupAddress->street,
            'number' => $pickupAddress->number,
            'line2' => is_null($pickupAddress->line2) ? '' : $pickupAddress->line2,
            'city' => $pickupAddress->city,
            'state' => $pickupAddress->state,
        ];

        $receiver = [
            'name' => ($shippingAddress->addressable_type === 'App\\Models\\Store') ? $order->store->name : $this->printAddressName($shippingAddress),
            'phone' => str_replace('+972', '0', $shippingAddress->phone),
            'street' => $shippingAddress->street,
            'number' => $shippingAddress->number,
            'line2' => is_null($shippingAddress->line2) ? '' : $shippingAddress->line2,
            'city' => $shippingAddress->city,
            'state' => $shippingAddress->state,
        ];

        if ($order->delivery->polygon->shipping_code->code === 'VELOAPPIO_LOCKER2LOCKER') {
            $receiver['name'] .= '(' . $order->delivery->external_service_id . ')';
        }

        $classification = ''
            . __('shipping_codes.no_date.' . $delivery->polygon->shipping_code->code)
            . ' '
            . __('misc.by')
            . ' '
            . __('couriers.' . $delivery->polygon->courier->name);

        $strRepo = new StringFormattingRepository();

        $lineNumber = (is_null($order->delivery->line_number)) ? '' : $order->delivery->line_number;
        $titles = [
            'senderTitle' => __('stickers.senderTitle'),
            'receiverTitle' => __('stickers.receiverTitle'),
            'timestamp' => __('stickers.timestamp'),
            'line_number' => __('stickers.line_number'),
            'order_number' => __('stickers.order_number'),
            'visit' => __('stickers.visit'),
        ];
        if (!$returnHtml) {
            foreach ($sender as $attr => $value) {
                $sender[$attr] = $strRepo->forPrint($value);
            }
            foreach ($receiver as $attr => $value) {
                $receiver[$attr] = $strRepo->forPrint($value);
            }
            foreach ($titles as $attr => $value) {
                $titles[$attr] = $strRepo->forPrint($value);
            }
            $classification = $strRepo->forPrint($classification);
            $lineNumber = $strRepo->forPrint($lineNumber);

        }

        $barcodeFormat = 'C128';
        if (!is_null($delivery->polygon->courier->barcode_format)) {
            $barcodeFormat = $delivery->polygon->courier->barcode_format;
        }

        $courierLogoSlug = explode(':', $delivery->polygon->courier->api);
        $courierLogoSlug = end($courierLogoSlug);

        $pdfData = [
            'orderName' => $order->name,
            'remoteId' => $delivery->remote_id,
            'lineNumber' => $lineNumber,
            'externalId' => (is_null($order->external_id)) ? '' : $order->external_id,
            'barcodeId' => $delivery->barcode,
            'barcodeSrc' => 'data:image/png;base64,' . DNS1D::getBarcodePNG(!is_null($delivery->barcode) ? $delivery->barcode : $delivery->remote_id, $barcodeFormat),
            'shippingCode' => $delivery->polygon->shipping_code,
            'timestamp' => $order->created_at,
            'sender' => $sender,
            'receiver' => $receiver,
            'titles' => $titles,
            'classification' => $classification,
            'veloLogo' => "data:image/png;base64," . base64_encode(file_get_contents(public_path('assets/images/stickers/logo.png'))),
            'courierLogo' => "data:image/png;base64," . base64_encode(file_get_contents(public_path('assets/images/stickers/couriers/' . $courierLogoSlug . '.png'))),
        ];

        if ($delivery) {
            switch ($delivery->polygon->courier->api) {
                case 'baldar':
                    $pdfData['externalId'] = $delivery->remote_id;
                    break;
            }
        }

        return view('sticker.body', $pdfData)->render();
    }

    public function getStickerHeader($order, $returnHtml = false)
    {
        $locale = $order->delivery->polygon->courier->locale;
        app()->setLocale($locale->iso);
        return view('sticker.header', [
            'pageWidth' => $this->pageWidth,
            'pageHeight' => $this->pageHeight,
            'order' => $order,
            'returnHtml' => $returnHtml,
            'rtl' => ($locale->iso === 'he'),
        ])->render();
    }

    public function getStickerFooter($returnHtml = false)
    {
        return view('sticker.footer', [
            'returnHtml' => $returnHtml,
        ])->render();
    }

    public function get($order)
    {
        if (!Storage::disk('public')->exists('stickers/' . $order->name . '.pdf')) {
            $targetPath = storage_path('app/public/stickers/' . $order->name . '.pdf');
            $filesystemResult = $this->createPathDirectoryStructure('stickers/' . $order->name . '.pdf');
            if (isset($filesystemResult['fail'])) {
                return $filesystemResult;
            }
            if (str_starts_with($order->delivery->barcode, 'https://')) {
                $ext = explode('.', $order->delivery->barcode);
                $ext = end($ext);
                $label = file_get_contents($order->delivery->barcode);
                if ($ext !== 'pdf') {
                    $sticker = Pdf::loadView('sticker/img', [
                        'order' => $order,
                        'img' => trim('data:' . MimeType::get($ext) . ';base64,' . base64_encode($label)),
                    ])->save($targetPath);
                } else {
                    $filesystemResult = $this->saveFile('stickers/' . $order->name . '.' . $ext, $label);
                }
            } else {
                $sticker = $this->getStickerHeader($order);
                $body = $this->getStickerBody($order);
                if (isset($body['fail'])) {
                    return $body;
                }
                $sticker .= $body;
                $sticker .= $this->getStickerFooter();
                $sticker = Pdf::setPaper([0, 0, $this->pageWidth, $this->pageHeight])->loadHtml($sticker);
                $sticker->save($targetPath);
            }
        }

        return config('app.url') . '/storage/stickers/' . $order->name . '.pdf';
    }

    public function getMulti($orders, $pageSize = null)
    {
        $stickersPerPage = 1;
        if ($pageSize === 'a4') {
            $this->pageHeight = 842;
            $this->pageWidth = 595;
            $stickersPerPage = 6;
        }

        $stickerMarkup = $this->getStickerHeader($orders->first(), true);

        foreach ($orders as $i => $order) {
            if ($i && $i % $stickersPerPage == 0) {
                $stickerMarkup .= view('sticker.page_break')->render();
            }

            $stickerBody = $this->getStickerBody($order, true);
            if (!isset($stickerBody['fail'])) {
                $stickerMarkup .= $stickerBody;
            }
        }
        $stickerMarkup .= $this->getStickerFooter(true);
        return $stickerMarkup;
    }
}
