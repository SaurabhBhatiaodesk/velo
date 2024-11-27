<?php

namespace App\Repositories\Couriers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Enums\DeliveryStatusEnum;
use App\Models\Courier;
use App\Repositories\OrderStatusRepository;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use Carbon\Carbon;
use Verdant\XML2Array;
use Log;


class ZigzagRepository extends CourierRepository
{
    private $apiRoot = '';
    private $user = '';
    private $password = '';

    public function __construct()
    {
        $this->apiRoot = rtrim(config('couriers.zigzag.api_root'), '/');
        $this->user = config('couriers.zigzag.user');
        $this->password = config('couriers.zigzag.password');
    }

    private function trackingApiCall($endpoint, $params, $debugData)
    {
        $query = '?' . http_build_query($params);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Content-Length' => strlen($query),
            ])->get($this->apiRoot . '/' . $endpoint . $query)->body();
        } catch (ConnectionException $e) {
            Log::info('zigzag track claim request fail', [
                'order' => [
                    'error' => $e->getMessage(),
                    'debugData' => $debugData,
                ],
            ]);
            return $this->fail('zigzag.trackFailed');
        }

        $response = XML2Array::createArray(str_replace('xmlns="Zigzag"', '', $response));

        if (!isset($response['DataSet']) || !isset($response['DataSet']['diffgr:diffgram'])) {
            Log::info('invalid zigzag tracking response - no dataset', [
                'endpoint' => $endpoint,
                'params' => $params,
                'response' => $response,
                'debugData' => $debugData,
            ]);
            return $this->fail('zigzag.trackFailed');
        } else if ($response['DataSet']['diffgr:diffgram'] === '') {
            if ($endpoint === 'LogStatus') {
                return [
                    'TAARIH' => Carbon::now()->toDateString(),
                    'SHAA' => Carbon::now()->toTimeString(),
                    'STATUS_CODE' => 53,
                ];
            } else {
                Log::info('invalid zigzag tracking response - empty dataset', [
                    'url' => $this->apiRoot . '/' . $endpoint . $query,
                    'endpoint' => $endpoint,
                    'params' => $params,
                    'response' => $response,
                    'debugData' => $debugData,
                ]);
                return $this->fail('zigzag.emptyTrackingResponse', [
                    'endpoint' => $endpoint,
                    'params' => $params,
                    'debugData' => $debugData,
                ]);
            }
        }
        return $response['DataSet']['diffgr:diffgram']['NewDataSet']['Table'];
    }

    /**
     * @param \App\Models\Order $order
     *
     * In case the barcode is duplicated, we add 2 digits to the order name and try again
     * @param bool $secondAttempt
     *
     * Delivery update data or fail
     * @return array
     */
    public function createClaim($order, $secondAttempt = false)
    {
        $translatedAddresses = $this->translateAddresses($order, true);
        $orderNote = '';

        if (strlen($order->note)) {
            $orderNote .= ' ' . $order->note;
        }

        if (strlen($orderNote) > 350) {
            $orderNote = substr($orderNote, 0, 50);
        }

        if (!is_null($order->delivery->dimensions)) {
            $orderNote = implode('CM * ', $order->delivery->dimensions) . 'CM ' . $order->delivery->weight . 'KG';
        }

        if (!preg_match('/^[-_a-zA-Z0-9]+$/D', $order->name)) {
            return $this->fail('store.invalidSlug');
        }

        $barcode = $this->limitBarcodeLength(strtoupper($order->name), 18);

        $params = [
            'UserName' => $this->user,
            'Password' => $this->password,
            'KOD_KIVUN' => $this->isReturn($order) ? 2 : 1, // 1 - normal, 2 - return, 4 - b2b
            'MOSER' => $translatedAddresses['pickup']->full_name,
            'HEVRA_MOSER' => (!is_null($translatedAddresses['pickup']->company_name)) ? $translatedAddresses['pickup']->company_name : '',
            'TEL_MOSER' => $this->formatPhoneNumberLocal($translatedAddresses['pickup']->phone),
            'EZOR_MOSER' => '',
            'SHM_EIR_MOSER' => $translatedAddresses['pickup']->city,
            'REHOV_MOSER' => $translatedAddresses['pickup']->street,
            'MISPAR_BAIT_MOSER' => $translatedAddresses['pickup']->number,
            'koma_MOSER' => '',
            'MEKABEL' => $translatedAddresses['shipping']->full_name,
            'HEVRA_MEKABEL' => (!is_null($translatedAddresses['shipping']->company_name)) ? $translatedAddresses['shipping']->company_name : '',
            'TEL_MEKABEL' => $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone),
            'EZOR_MEKABEL' => '',
            'SHM_EIR_MEKABEL' => $translatedAddresses['shipping']->city,
            'REHOV_MEKABEL' => $translatedAddresses['shipping']->street,
            'MISPAR_BAIT_MEKABEL' => $translatedAddresses['shipping']->number,
            'koma_MEKABEL' => '',
            'SUG_SHLIHUT' => $this->isReplacement($order) ? 2 : 0, // 0 - normal, 2 - double, 33 - govayna
            'HEAROT' => $orderNote,
            'SHEM_MAZMIN' => '',
            'MICROSOFT_ORDER_NUMBER' => $barcode,
            'HEAROT_LKTOVET_MKOR' => is_null($translatedAddresses['pickup']->line2) ? '' : $translatedAddresses['pickup']->line2,
            'HEAROT_LKTOVET_YAAD' => is_null($translatedAddresses['shipping']->line2) ? '' : $translatedAddresses['shipping']->line2,
            'SHEM_CHEVRA' => '',
            'TEOR_TKALA' => '',
            'KARTONIM' => '1',
        ];

        $query = '?' . http_build_query($params);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Content-Length' => strlen($query),
            ])->get($this->apiRoot . '/INSERT_SHLIHUT2' . $query)->body();
        } catch (ConnectionException $e) {
            Log::info('zigzag create claim request fail for order ' . $order->name, [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            return $this->fail($e->getMessage());
        }
        $response = XML2Array::createArray(str_replace('xmlns="Zigzag"', '', $response));
        if (!isset($response['ArrayOfLong'])) {
            Log::info('createClaim failed for order ' . $order->name, [
                'url' => $this->apiRoot . '/INSERT_SHLIHUT2',
                'params' => $params,
                'response' => $response,
            ]);
            return $this->fail('delivery.createClaimFailed');
        }
        $remoteId = $response['ArrayOfLong']['long'][0];
        $lineNum = $response['ArrayOfLong']['long'][1];
        // -6 - barcode already exists
        if (intVal($remoteId) === -6 && !$secondAttempt) {
            // track the order
            $response = $this->trackingApiCall('LogStatus', [
                'UserName' => $this->user,
                'Password' => $this->password,
                'numerator' => $barcode,
                'MisparLakoah' => $this->user,
            ], [
                'Order Name' => $order->name
            ]);

            if (isset($response['fail'])) {
                return $this->fail($response);
            }

            if (!isset($response['TAARIH'])) {
                return $this->fail('delivery.createClaimFailed');
            }

            return [
                'barcode' => $barcode,
                'remote_id' => isset($response['NUMERATOR']) ? $response['NUMERATOR'] : '0',
                'courier_responses' => [
                    [
                        'date' => Carbon::parse($response['TAARIH'] . ' ' . $response['SHAA']),
                        'code' => $response['STATUS_CODE'],
                    ],
                ],
            ];
        } else if ($remoteId === false || intVal($remoteId) <= 0) {
            Log::info('createClaim failed for order ' . $order->name, [
                'url' => $this->apiRoot . '/INSERT_SHLIHUT2',
                'params' => $params,
                'response' => $response,
                'received remoteId' => $remoteId,
                'received lineNum' => $lineNum,
            ]);
            return $this->fail('delivery.createClaimFailed');
        }

        return [
            'barcode' => $barcode,
            'remote_id' => $remoteId,
            'line_number' => $lineNum,
            'courier_responses' => [],
        ];
    }

    public function getUpdateData($order, $code, $sugShlihut = false)
    {
        $updateData = [];
        if (!$order || !$order->delivery->courier_status || strval($order->delivery->courier_status) !== strval($code)) {
            $updateData['courier_status'] = $code;
            switch ($code) {
                case 1: // הוזמן
                case 2: // שליח בדרך לאיסוף
                    $updateData = [
                        'status' => DeliveryStatusEnum::Accepted->value,
                    ];
                    break;

                case 30: //	כתובת לא נכונה
                    $updateData = [
                        'status' => DeliveryStatusEnum::TransitToDestination->value,
                    ];
                    break;

                case 32: //	נמסר ZIG
                case 33: //	נמסר
                    // SUG_SHLIHUT
                    // 42: doubles - side 1
                    // 52: doubles - side 2
                    // 48: govayna doubles - side 1
                    // 43: govayna doubles - side 2
                    if (!$order || $order->delivery->is_replacement && $sugShlihut !== 52 && $sugShlihut !== 43 && $order->delivery->accepted_at->isAfter(Carbon::now()->subDays(3))) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Transit->value,
                        ];
                    } else {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Delivered->value,
                            'delivered_at' => Carbon::now(),
                        ];
                        if ($order) {
                            $repo = new OrderStatusRepository();
                            $result = $repo->complete($order);
                            if (isset($result['fail'])) {
                                return $this->fail($result);
                            }
                        }
                    }
                    break;

                case 88: //	נמסר שליח חיצוני
                    $updateData = [
                        'status' => DeliveryStatusEnum::Delivered->value,
                        'delivered_at' => Carbon::now(),
                    ];
                    break;

                case 3: //	נאסף על ידי שליח
                case 4: //	שליח בדרך למסירה
                case 16: //	לקוח ביקש להגיע במועד אחר
                case 18: //	איסוף עצמי בסניף
                case 22: //	ניסיון ביצוע פעם שנייה ללא הצלחה
                case 24: //	נסרק ע"י שליח
                case 25: //	נסרק במחסן זיגזג
                case 27: //	נדחה ליום עסקים הבא
                case 29: //	נמסרה שליחות חלק ראשון
                case 31: //	חוזר ללקוח - באישורו
                case 43: //	ניתן להשאיר ליד הדלת
                case 47: //	הועבר לשליח משנה
                case 48: //	שטח צבאי סגור - אין כניסה לאזרחים
                case 49: //	מבקש לשנות תיאום
                case 50: //	אין מענה לניסיון תיאום
                case 51: //	לחזור לנמען במועד אחר
                case 55: //	מבקש קשר עם לקוח על
                case 57: //	מעוכב להלשמת פרטים
                case 58: //	הקפאה בהוראת לקוח
                case 59: //	עודכנו פרטים - לביצוע מחדש
                case 60: //	נשלח SMS תיאום
                case 61: //	נשלח SMS תזכורת
                case 66: //	תיאום שליח חיצוני
                case 65: //	נשלח SMS דחיית משלוח
                case 67: //	ממתין לעדכון שליח חיצוני
                case 68: //	השלמת מסירה (לא הכל נמסר)
                case 69: //	נמסר בכתובת שגויה
                case 87: //	נסרק שליח חיצוני
                case 89: //	נמסר צד ראשון של כפולה שליח חיצוני
                case 91: //	עבר לסניף תיאומים להמשך טיפול
                case 100: // נסרק במחסן זיגזג
                case 102: // נקבעו מועד תיאום
                case 108: // אזור חלוקה 72 שעות
                case 201: // סגירת חזרות
                case 204: // סגירת לדוח אחרי אקסל
                    $updateData = [
                        'status' => DeliveryStatusEnum::Transit->value,
                        'pickup_at' => Carbon::now(),
                    ];
                    break;

                case 53: //	ביטל הזמנה
                    $updateData = [
                        'status' => DeliveryStatusEnum::Cancelled->value,
                        'cancelled_at' => Carbon::now(),
                    ];
                    break;

                case 23: //	לא ניתן לביצוע לא מגיעים לישוב
                case 35: //	לא נכח בכתובת בעת הגעת השליח
                case 36: //	לקוח (על) ביקש לסגור את השליחות
                case 37: //	לא אותר בכתובת ואין מענה
                case 40: //	אין חומר באיסוף
                case 41: //	משלוח אבד
                case 90: //	לא הגיע לחברת משלוחים
                case 222: //	ללא כתובת מדוייקת
                    $updateData = [
                        'status' => DeliveryStatusEnum::Failed->value,
                    ];
                    break;
            }
        }

        return $updateData;
    }

    public function trackClaim($order)
    {
        return $order;
        $response = $this->trackingApiCall('LogStatus', [
            'UserName' => $this->user,
            'Password' => $this->password,
            'numerator' => $order->delivery->barcode,
            'MisparLakoah' => $this->user,
        ], [
            'Order Name' => $order->name
        ]);
        if (isset($response['fail'])) {
            return $order;
        }

        $courierResponses = [];
        if (isset($response['TAARIH'])) {
            $courierResponses[] = [
                'date' => Carbon::parse($response['TAARIH'] . ' ' . $response['SHAA']),
                'code' => $response['STATUS_CODE'],
            ];
        } else {
            foreach ($response as $i => $statusLine) {
                $courierResponses[] = [
                    'date' => Carbon::parse($statusLine['TAARIH'] . ' ' . $statusLine['SHAA']),
                    'code' => $statusLine['STATUS_CODE'],
                ];
            }
        }

        if (
            count($courierResponses) && (
                $courierResponses[0]['code'] !== $order->delivery->courier_status ||
                intVal($courierResponses[0]['code']) === 33 ||
                intVal($courierResponses[0]['code']) === 32 ||
                intVal($courierResponses[0]['code']) === 88
            )
        ) {
            $updateData = $this->getUpdateData($order, $courierResponses[0]['code'], isset($response['SUG_SHLIHUT']) ? intVal($response['SUG_SHLIHUT']) : false);

            if (!empty($updateData)) {
                $updateData = array_merge($updateData, [
                    'courier_responses' => $courierResponses,
                ]);

                if (!$order->delivery->update($updateData)) {
                    return $this->fail('delivery.updateFailed');
                }
            }
        }

        return $order;
    }

    public function trackClaimsLegacy($courier = false, $fromDate = false, $toDate = false, $logReport = false)
    {
        return [];
        $statusesToTrack = [
            DeliveryStatusEnum::Accepted->value,
            DeliveryStatusEnum::PendingPickup->value,
            DeliveryStatusEnum::Transit->value,
            DeliveryStatusEnum::TransitToDestination->value,
            DeliveryStatusEnum::TransitToWarehouse->value,
            DeliveryStatusEnum::TransitToSender->value,
            DeliveryStatusEnum::InWarehouse->value,
        ];

        if (!$courier) {
            $courier = Courier::where('api', 'zigzag')->first();
        }
        if (!$fromDate) {
            $fromDate = Carbon::now()->subDays(14);
            $oldestActiveOrder = $courier->deliveries()
                ->whereIn('status', $statusesToTrack)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($oldestActiveOrder && $oldestActiveOrder->created_at->isAfter($fromDate)) {
                $fromDate = $oldestActiveOrder->created_at;
            }
        }

        if (!$toDate) {
            $toDate = Carbon::now();
        }

        $response = $this->trackingApiCall('getStatusShlihutYomiAndHistoriaByDate', [
            'UserName' => $this->user,
            'Password' => $this->password,
            'FromDate' => $fromDate->format('Ymd'),
            'ToDate' => $toDate->format('Ymd'),
        ], 'Legacy Call');

        if (isset($response['fail'])) {
            return $response;
        }

        if (isset($response['CODE_STATUS'])) {
            $response = [$response];
        }

        $idsToCheck = [];
        foreach ($response as $deliveryData) {
            $idsToCheck[] = $deliveryData['NUMERATOR'];
            $idsToCheck[] = $deliveryData['MicrosoftOrderNumber'];
        }

        $deliveries = null;
        foreach (array_chunk($idsToCheck, 500) as $i => $chunk) {
            $chunkResults = $courier->deliveries()
                ->whereNotIn('status', $statusesToTrack)
                ->where(function ($query) use ($chunk) {
                    foreach ($chunk as $id) {
                        $query->orWhere('remote_id', 'LIKE', '%' . $id . '%');
                        $query->orWhere('barcode', 'LIKE', '%' . $id . '%');
                    }
                })
                ->get();

            if (!is_null($chunkResults) && $chunkResults->count()) {
                if (is_null($deliveries)) {
                    $deliveries = $chunkResults;
                } else {
                    $deliveries->merge($chunkResults);
                }
            }
        }

        foreach ($deliveries as $i => $delivery) {
            $deliveryIndex = null;
            foreach ($response as $j => $deliveryData) {
                switch ($delivery->status->value) {
                    case 'cancelled':
                    case 'delivered':
                    case 'rejected':
                    case 'refunded':
                    case 'failed':
                        continue 2;
                }
                $updateData = $this->getUpdateData(false, intVal($deliveryData['CODE_STATUS']), isset($deliveryData['SUG_SHLIHUT']) ? intVal($deliveryData['SUG_SHLIHUT']) : false);

                $response[$j]['delivery_type'] = 'רגילה';
                if (isset($deliveryData['SUG_SHLIHUT'])) {
                    switch (intVal($deliveryData['SUG_SHLIHUT'])) {
                        case 42:
                        case 52:
                        case 48:
                        case 43:
                            $response[$j]['delivery_type'] = 'כפולה';
                            break;
                    }
                }

                if (isset($updateData['status'])) {
                    $response[$j]['velo_status'] = $updateData['status'];
                }

                if (
                    $delivery->barcode === $deliveryData['NUMERATOR'] ||
                    $delivery->barcode === $deliveryData['MicrosoftOrderNumber'] ||
                    !isset($deliveryData['velo_status'])
                ) {
                    $deliveryIndex = $delivery->barcode;
                } else if (
                    $delivery->remote_id === $deliveryData['NUMERATOR'] ||
                    $delivery->remote_id === $deliveryData['MicrosoftOrderNumber'] ||
                    !isset($deliveryData['velo_status'])
                ) {
                    $deliveryIndex = $j;
                }
            }

            if (is_null($deliveryIndex)) {
                continue;
            }

            if ($delivery->is_replacement) {
                $response[$j]['delivery_type'] = 'כפולה';
            } else if ($delivery->is_return) {
                $response[$j]['delivery_type'] = 'איסוף';
            }

            if (intVal($delivery->courier_status) !== intVal($response[$j]['CODE_STATUS']) && !empty($updateData)) {
                $updateData['courier_responses'] = $delivery->courier_responses;
                $updateData['courier_responses'][] = [
                    'date' => Carbon::create($response[$j]['STATUS_TAARICH']),
                    'code' => intVal($response[$j]['CODE_STATUS']),
                ];

                if ($delivery->update($updateData)) {
                    DeliveryUpdated::dispatch($delivery);
                }
            }
            $deliveries->forget($i);
        }

        if ($logReport) {
            $result = [
                'כפילויות' => [],
                'חוסרים' => [],
            ];
            foreach ($response as $i => $deliveryData) {
                $suspectedDuplicates = $courier->deliveries()
                    ->where(function ($query) use ($deliveryData) {
                        $query->where('remote_id', 'LIKE', '%' . $deliveryData['NUMERATOR'] . '%')
                            ->orWhere('remote_id', 'LIKE', '%' . $deliveryData['MicrosoftOrderNumber'] . '%')
                            ->orWhere('barcode', 'LIKE', '%' . $deliveryData['NUMERATOR'] . '%')
                            ->orWhere('barcode', 'LIKE', '%' . $deliveryData['MicrosoftOrderNumber'] . '%');
                    })
                    ->get()
                    ->toArray();

                foreach ($suspectedDuplicates as $j => $delivery) {
                    if (
                        trim($delivery['remote_id']) === trim($deliveryData['NUMERATOR']) &&
                        trim($delivery['barcode']) === trim($deliveryData['MicrosoftOrderNumber'])
                    ) {
                        unset($response[$i]);
                        continue 2;
                    }
                }

                $result[count($suspectedDuplicates) ? 'כפילויות' : 'חוסרים'][] = [
                    'מזהה' => is_string($deliveryData['NUMERATOR']) ? $deliveryData['NUMERATOR'] : '',
                    'נומרטור' => is_string($deliveryData['NUMERATOR_ZIGZAG']) ? $deliveryData['NUMERATOR_ZIGZAG'] : '',
                    'ברקוד' => is_string($deliveryData['MicrosoftOrderNumber']) ? $deliveryData['MicrosoftOrderNumber'] : '',
                    'סוג משלוח' => isset($deliveryData['delivery_type']) ? $deliveryData['delivery_type'] : '',
                    'מוסר' => is_string($deliveryData['MOSER']) ? $deliveryData['MOSER'] : '',
                    'מקבל' => is_string($deliveryData['MEKABEL']) ? $deliveryData['MEKABEL'] : '',
                    'תאריך' => is_string($deliveryData['TAARICH']) ? $deliveryData['TAARICH'] : '',
                    'סטטוס' => isset($deliveryData['velo_status']) ? $deliveryData['velo_status'] : '',
                    'קוד סטטוס' => is_string($deliveryData['CODE_STATUS']) ? $deliveryData['CODE_STATUS'] : '',
                    'תאריך סטטוס' => Carbon::create($deliveryData['STATUS_TAARICH'])->format('Y-m-d H:i'),
                    'כתובת איסוף' => is_string($deliveryData['KTOVET_MAKOR']) ? $deliveryData['KTOVET_MAKOR'] : '',
                    'הערות לכתובת מקור' => is_string($deliveryData['HEAROT_LKTOVET_MKOR']) ? $deliveryData['HEAROT_LKTOVET_MKOR'] : '',
                    'כתובת יעד' => is_string($deliveryData['KTOVET_YAAD']) ? $deliveryData['KTOVET_YAAD'] : '',
                    'הערות לכתובת יעד' => is_string($deliveryData['HEAROT_LKTOVET_YAAD']) ? $deliveryData['HEAROT_LKTOVET_YAAD'] : '',
                ];

                if (count($suspectedDuplicates)) {
                    $deliveryType = 'רגילה';
                    if ($delivery['is_replacement']) {
                        $deliveryType = 'כפולה';
                    } else if ($delivery['is_return']) {
                        $deliveryType = 'איסוף';
                    }
                    foreach ($suspectedDuplicates as $duplicate) {
                        $result['כפילויות'][] = [
                            'מזהה' => isset($duplicate['remote_id']) ? $duplicate['remote_id'] : '',
                            'נומרטור' => isset($duplicate['remote_id']) ? $duplicate['remote_id'] : '',
                            'ברקוד' => isset($duplicate['barcode']) ? $duplicate['barcode'] : '',
                            'סוג משלוח' => $deliveryType,
                            'מוסר' => $duplicate['pickup_address']['first_name'] . ' ' . $duplicate['pickup_address']['last_name'],
                            'מקבל' => $duplicate['shipping_address']['first_name'] . ' ' . $duplicate['shipping_address']['last_name'],
                            'תאריך' => Carbon::create($duplicate['accepted_at'] ? $duplicate['accepted_at'] : $duplicate['created_at'])->format('Y-m-d H:i'),
                            'סטטוס' => $duplicate['status'],
                            'קוד סטטוס' => $duplicate['courier_status'],
                            'תאריך סטטוס' => '',
                            'כתובת איסוף' => $duplicate['pickup_address']['street'] . ' ' . $duplicate['pickup_address']['number'] . ', ' . $duplicate['pickup_address']['city'],
                            'הערות לכתובת מקור' => isset($duplicate['pickup_address']['line2']) ? $duplicate['pickup_address']['line2'] : '',
                            'כתובת יעד' => $duplicate['shipping_address']['street'] . ' ' . $duplicate['shipping_address']['number'] . ', ' . $duplicate['shipping_address']['city'],
                            'הערות לכתובת יעד' => isset($duplicate['shipping_address']['line2']) ? $duplicate['shipping_address']['line2'] : '',
                        ];
                    }
                    $result['כפילויות'][] = [
                        'מזהה' => '',
                        'נומרטור' => '',
                        'ברקוד' => '',
                        'סוג משלוח' => '',
                        'מוסר' => '',
                        'מקבל' => '',
                        'תאריך' => '',
                        'סטטוס' => '',
                        'קוד סטטוס' => '',
                        'תאריך סטטוס' => '',
                        'כתובת איסוף' => '',
                        'הערות לכתובת מקור' => '',
                        'כתובת יעד' => '',
                        'הערות לכתובת יעד' => '',
                    ];
                }
            }

            return $result;
        }

        return [];
    }
}
