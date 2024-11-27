<?php

namespace App\Services;

use App\Repositories\Sms\Sms4freeRepository;

class SmsService
{
    private $repo;

    /*
     * Send an SMS
     *
     * @param array|string $recipients (phone numbers)
     * @param string $message
     * @return \App\Models\SmsLog | array (fail)
     */
    public static function sendSms($recipients, $msg)
    {
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }
        if (!count($recipients)) {
            return ['fail' => true, 'error' => 'No recipients', 'code' => 400];
        }
        if (!strlen($msg)) {
            return ['fail' => true, 'error' => 'No message', 'code' => 400];
        }
        $repo = new Sms4freeRepository();
        return $repo->sendSms($recipients, $msg);
    }

    /*
     * Check how many SMS messages are available in the account
     *
     * @return int | array (fail)
     */
    public static function getSmsBalance()
    {
        $repo = new Sms4freeRepository();
        return $repo->getSmsBalance();
    }
}
