<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Order;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'beneficiary_number' => [
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
                function ($attribute, $value, $fail) {
                    $existingOrder = Order::where('beneficiary_number', $value)
                        ->whereIn('status', ['pending', 'processing'])
                        ->first();
                        
                    if ($existingOrder) {
                        $fail('An order with this beneficiary number is already pending or processing.');
                    }
                }
            ],
            'network_id' => 'required|integer',
            'size' => 'required|string'
        ];
    }

    public function messages()
    {
        return [
            'beneficiary_number.regex' => 'The beneficiary number must be exactly 10 digits.',
            'beneficiary_number.required' => 'The beneficiary number is required.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}