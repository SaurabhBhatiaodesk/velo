<?php

namespace App\Http\Requests\Models\PaymentMethods;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\ApiRequest;
use App\Models\PaymentMethod;

class SetDefaultRequest extends ApiRequest
{
    private $paymentMethod;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $this->paymentMethod = PaymentMethod::find($this->id);
        return (!!$this->paymentMethod && $this->user()->can('update', $this->paymentMethod));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id' => 'required|numeric',
        ];
    }
}
