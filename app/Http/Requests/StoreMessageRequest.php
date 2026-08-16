<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation by injecting the authenticated user's ID into auth_id.
     */
    protected function prepareForValidation()
    {
        if ($this->user()) {
            $this->merge([
                'auth_id' => $this->user()->id,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sender_id' => ['required', 'integer'],
            'auth_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Override failed validation to return custom flat error payload.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'detail' => 'Invalid message data provided.'
        ], Response::HTTP_BAD_REQUEST));
    }
}