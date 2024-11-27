<?php

namespace App\Repositories\AI;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class OpenAiRepository extends BaseRepository
{
    private $apiRoot;
    private $apiKey;
    private $projectId;
    private $organization;

    public function __construct()
    {
        $this->apiRoot = config('services.openAi.api_root');
        $this->apiKey = config('services.openAi.api_key');
        $this->projectId = config('services.openAi.project_id');
        $this->organization = config('services.openAi.organization');
    }

    /**
     * Make an API request
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @param bool $isSecondAttempt
     * @return array
     */
    private function makeApiRequest($endpoint, $data = [], $method = 'post', $isSecondAttempt = false)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->apiKey}",
        ];

        if (!empty($this->organization)) {
            $headers['OpenAI-Organization'] = $this->organization;
        }
        if (!empty($this->projectId)) {
            $headers['OpenAI-Project'] = $this->projectId;
        }

        try {
            $response = Http::withHeaders($headers)
                ->baseUrl($this->apiRoot)
                ->send($method, $endpoint, ['json' => $data])
                ->body();
        } catch (ConnectionException $e) {
            return $this->fail('openAi.connectionException', 500, [
                'error' => $e->getMessage(),
            ], 'makeApiRequest');
        }

        $response = json_decode($response, true);
        if (!empty($response['error'])) {
            return $this->fail('openAi.error', 500, [
                'error' => $response['error'],
            ], 'makeApiRequest');
        }
        if (empty($response['choices'])) {
            return $this->fail('openAi.noChoices', 500, [
                'response' => $response,
            ], 'makeApiRequest');
        }

        $results = [];
        foreach ($response['choices'] as $i => $choice) {
            $results[] = $this->decodeEscapedJson($choice['message']['content']);
        }

        return array_values($results);
    }

    /**
     * Generate a response from the OpenAI API
     * @param string $prompt
     * @param string $model
     * @param int $maxTokens
     * @return array
     */
    public function prompt(string $prompt, string $model = 'gpt-3.5-turbo', int $maxTokens = 150)
    {
        $response = $this->makeApiRequest('chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ],
            ],
            'max_tokens' => $maxTokens,
        ]);

        /*
         chat completions response

        {
            "id": "chatcmpl-123456",
            "object": "chat.completion",
            "created": 1728933352,
            "model": "gpt-4o-2024-08-06",
            "choices": [
                {
                "index": 0,
                "message": {
                    "role": "assistant",
                    "content": "Hi there! How can I assist you today?",
                    "refusal": null
                },
                "logprobs": null,
                "finish_reason": "stop"
                }
            ],
            "usage": {
                "prompt_tokens": 19,
                "completion_tokens": 10,
                "total_tokens": 29,
                "prompt_tokens_details": {
                "cached_tokens": 0
                },
                "completion_tokens_details": {
                "reasoning_tokens": 0,
                "accepted_prediction_tokens": 0,
                "rejected_prediction_tokens": 0
                }
            },
            "system_fingerprint": "fp_6b68a8204b"
        }



         */
        return $response;
    }
}
