<?php

namespace App\Repositories\Sms;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use App\Repositories\BaseRepository;

// https://sms4free.co.il/outcome-sms-api.html
class Sms4freeRepository extends BaseRepository
{
    private $apiRoot;
    private $apiKey;
    private $user;
    private $password;
    private $sender;

    public function __construct()
    {
        $this->apiRoot = config('services.sms4free.api_root');
        $this->apiKey = config('services.sms4free.api_key');
        $this->user = config('services.sms4free.user');
        $this->password = config('services.sms4free.password');
        $this->sender = config('services.sms4free.sender');
    }

    /**
     * Make an API request
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    public function makeApiRequest($endpoint, $data = [], $method = 'post')
    {
        $data = array_merge($data, [
            'key' => $this->apiKey,
            'user' => $this->user,
            'pass' => $this->password,
        ]);

        $response = Http::baseUrl($this->apiRoot . '/ApiSMS/')
            ->withHeaders([
                'Content-Type' => 'application/json',
            ]);

        // make the request
        try {
            if (strtolower($method) === 'post') {
                $response = $response
                    ->withBody(json_encode($data), 'application/json')
                    ->post($endpoint, $data);
            } else {
                $response = $response->send($method, $endpoint, $data);
            }
            $response = $response->body();
        } catch (ConnectionException $e) {
            return $this->fail('sms4free.connectionError', $e->getCode(), [
                'method' => strtoupper($method),
                'url' => $this->apiRoot . '/api/' . $endpoint,
                'payload' => $data,
                'error' => $e->getMessage(),
            ], 'makeApiRequest');
        }

        $response = json_decode($response, true);

        // return the response
        return $response;
    }


    /*
     * Send an SMS
     *
     * @param array $recipients (phone numbers)
     * @param string $message
     * @return SmsLog | array (fail)
     */
    public function sendSms($recipients, $message)
    {
        // add environment indicator to the message
        switch (config('app.env')) {
            case 'local':
            case 'development':
            case 'staging':
                $message = '(' . substr(config('app.env'), 0, 3) . ') ' . $message;
                break;
        }

        $response = $this->makeApiRequest('v2/SendSMS', [
            'sender' => $this->sender,
            'recipient' => implode(',', $recipients),
            'msg' => $message,
        ]);

        if (!empty($response['fail'])) {
            return $response;
        }

        if ($response['status'] <= 0) {
            $smsLog = SmsLog::create([
                'recipient' => $recipients,
                'message' => $message,
                'success' => false,
            ]);
            return $this->fail('sms4free.sendSmsError', 500, [
                'response' => $response,
            ], 'sendSms');
        }

        $smsLog = SmsLog::create([
            'recipient' => implode(',', $recipients),
            'message' => $message,
            'success' => true,
        ]);

        if (!$smsLog) {
            return $this->fail('sms4free.smsLogCreateError', 500, [
                'recipient' => $recipients,
                'message' => $message,
                'success' => empty($response['fail'])
            ], 'sendSms');
        }

        return $smsLog;
    }


    /*
     * Check how many SMS messages are available in the account
     *
     * @return int | array (fail)
     */
    public function getSmsBalance()
    {
        $response = $this->makeApiRequest('AvailableSMS');
        if (!empty($response['fail'])) {
            return $response;
        }
        return intval($response);
    }
}
