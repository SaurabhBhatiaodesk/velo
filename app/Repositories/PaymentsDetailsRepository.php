<?php

namespace App\Repositories;

use App\Exports\Support\CustomTabsExport;
use App\Mail\Admin\Report;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;


class PaymentsDetailsRepository
{




    static function paymentsDetails(Request $request)
    {


        $fromDate = Carbon::createFromFormat('Y-m-d', "{$request->year}-{$request->month}-01");
        $toDate = Carbon::createFromFormat('Y-m-d', "{$request->year}-{$request->month}-01")->endOfMonth();

        $titles = [
            "Store",
            "From Deliveries",
            "From Transactions",
            "From Subscription",
            "Total Amount",
            "Total Payments",
            "Total Paid",
        ];

        $data = [
            'Summary' => self::paymentsDetailsData($fromDate, $toDate)
        ];


        foreach ($data['Summary'] as $datum){
            $bills = Bill::selectRaw('created_at, billable_type, total, transaction_id')
                ->where('store_slug', '=', $datum['Store'])
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->get();

            foreach ($bills as $bill) {
                if(!isset($data[ $datum['Store']])) $data[ $datum['Store'] ] = [];
                $data[ $datum['Store'] ][] = [
                    'created_at'=> $bill->created_at->format('M d Y'),
                    'billable_type'=> $bill->billable_type,
                    'total'=> $bill->total,
                    'transaction_id'=> $bill->transaction_id,
                ];
                if($bill->transaction_id) {
                    $data['Summary'][$datum['Store']]['TotalPaid'] += $bill->total;
                }
            }

        }

        foreach ($data['Summary'] as $k=>$item) {
            if($item['TotalAmount'] == 0){
                unset($data['Summary'][$k]);
                unset($data[$k]);
            }
        }

        foreach ($data as $k=>$item) {
            if($k !== 'Summary') {
                array_unshift($data[$k], [
                    'Create',
                    'Type',
                    'Total',
                    'Transaction'
                ]);
            }
        }
        array_unshift($data['Summary'], $titles);

        //        return response()->json($data);

        return Excel::download(new CustomTabsExport($data), 'Payment_Details_'. "{$request->year}-{$request->month}" .'.xlsx');

//        LateOrdersRepository::mail(
//            auth()->user()->email,
//            "Late Orders History:  $fromDate - $toDate ",
//            $data
//        );
//        return response()->json(['success' => true]);

    }

    static function paymentsDetailsData($fromDate, $toDate){
        //Select all bills of the month grouped by store
        $bills = Bill::selectRaw('store_slug, billable_type, SUM(total) as total, COUNT(*) as counter')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('enterprise_billing','=','0')
            ->groupBy(['store_slug', 'billable_type'])
            ->orderBy('store_slug')
            ->get();

        // Summarize data per store
        $data = [];
        foreach ($bills as $bill) {
            $type = substr($bill->billable_type, strrpos($bill->billable_type,'\\')+1);
            if(!isset($data[$bill->store_slug])) $data[$bill->store_slug] = [
                'Store'=>$bill->store_slug,
                'Delivery'=>0,
                'Transaction'=>0,
                'Subscription'=>0,
                'TotalAmount'=>0,
                'TotalPayments'=>0,
                'TotalPaid'=>0,

            ];
            $data[$bill->store_slug][$type] = $bill->total;
            $data[$bill->store_slug]['TotalAmount'] += $bill->total;
            $data[$bill->store_slug]['TotalPayments'] += $bill->counter;
        }

        return $data;
    }

    static function mail($address, $subject, $data)
    {
        return Mail::to($address)->send(
            new Report(
                $subject,
                \Illuminate\Support\Carbon::now(),
                Excel::raw(new CustomTabsExport($data), \Maatwebsite\Excel\Excel::XLSX)
            )
        );
    }

}
