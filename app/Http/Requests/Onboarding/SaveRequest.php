<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Store;
use App\Http\Requests\ApiRequest;

class SaveRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!$this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'details.name' => ['string'],
            'details.website' => ['url', 'nullable'],
            'details.first_name' => ['string'],
            'details.last_name' => ['string'],
            'details.phone' => ['string'],

            'details.addresses.*.first_name' => ['string'],
            'details.addresses.*.last_name' => ['string'],
            'details.addresses.*.phone' => ['string'],
            'details.addresses.*.street' => ['string'],
            'details.addresses.*.number' => ['string'],
            'details.addresses.*.line2' => ['string', 'nullable'],
            'details.addresses.*.city' => ['string'],
            'details.addresses.*.state' => ['string', 'nullable'],
            'details.addresses.*.country' => ['string'],

            'plan.plan.id' => ['required', 'numeric'],

            'payment.card.cardMask' => ['required', 'string'],
            'payment.card.cardholderName' => ['required', 'string'],
            'payment.card.expiry' => ['required', 'numeric'],
            'payment.payerEmail' => ['required', 'email'],
            'payment.payerName' => ['required', 'string'],
            'payment.payerPhone' => ['required', 'string'],
            'payment.payerSocialId' => ['nullable', 'numeric'],
            'payment.token' => ['required', 'string'],
            'payment.promoCode' => ['string', 'nullable'],
            'payment.terms' => ['required', 'boolean'],
        ];
    }
}
