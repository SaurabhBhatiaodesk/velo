<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

// reCaptcha dependencies
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $repo;

    protected function respond($data = [], $code = 200)
    {
        return response()->json($data, $code);
    }

    protected function fail($fail)
    {
        return response()->json($fail, $fail['code']);
    }

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
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $data = [])
    {
        $user = auth()->user();
        if (!$user) {
            return $this->respond([
                'message' => 'tokenExpired',
                'code' => 401,
            ], 401);
        }
        $user->load('stores', 'team_stores');
        $user->stores->load('plan_subscription');
        if (!isset($data['zendesk_jwt'])) {
            $data['zendesk_jwt'] = $user->getZendeskToken();
        }
        return $this->respond([
            'jwt' => $token,
            'user' => array_merge($user->toArray(), ['roles' => $user->roles()->pluck('name')]),
            'expiry' => auth()->factory()->getTTL() * 60,
            'data' => $data
        ]);
    }
}
