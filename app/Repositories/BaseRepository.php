<?php

namespace App\Repositories;

// reCaptcha dependencies
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use Log;

class BaseRepository
{
    public $model = null;

    private function validateRecaptcha($request)
    {
        if (
            config('google.project_id') &&
            strlen(config('google.project_id')) &&
            config('google.account_filename') &&
            strlen(config('google.account_filename'))
        ) {
            if (!config('google.application_credentials')) {
                config(['google.application_credentials' => realpath(__DIR__ . '/../../../' . config('google.account_filename'))]);
            }

            $client = new RecaptchaEnterpriseServiceClient([
                'keyFilePath' => config('google.application_credentials'),
                'projectId' => config('google.project_id')
            ]);

            $projectName = $client->projectName(config('google.project_id'));
            $event = (new Event())->setSiteKey(config('google.recaptcha_site_key'))->setToken($request->input('captcha'));
            $assessment = (new Assessment())->setEvent($event);

            try {
                $response = $client->createAssessment($projectName, $assessment);

                // You can use the score only if the assessment is valid,
                // In case of failures like re-submitting the same token, getValid() will return false
                if ($response->getTokenProperties()->getValid() == false) {
                    $request->validate(['captcha' => 'size:2'], ['captcha.size' => InvalidReason::name($response->getTokenProperties()->getInvalidReason())]);
                }
            } catch (\Exception $e) {
                $request->validate(['captcha' => 'size:2'], ['captcha.size' => 'CreateAssessment() call failed with the following error: ', $e]);
            }
        }
    }

    protected function validateRequest($request)
    {
        $this->validateRecaptcha($request);
        return $request->all();
    }

    /**
     * Decodes escaped json where applicable
     * returns the decoded json array, or the original string
     *
     * @param string $str
     * @return array|string
     */
    protected function decodeEscapedJson($str)
    {
        if (preg_match('/\[\s*\{.*?\}\s*\]/s', $str, $matches)) {
            return json_decode($matches[0], true);
        }
        return $str;
    }

    protected function fail($error = '', $code = 500, $data = [], $function = '')
    {
        $log = 'Repository fail in ' . get_class($this);
        if (strlen($function)) {
            $log .= '@' . $function;
        }
        Log::info($log, [
            'error' => $error,
            'code' => $code,
            'data' => $data
        ]);
        return [
            'fail' => true,
            'error' => strlen($error) ? $error : 'error.' . $code,
            'code' => $code,
        ];
    }
}
