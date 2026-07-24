<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class StoreUserCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'              => [
                'required', 
                'string', 
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
            'password_confirmation' => ['required', 'string'],

            'name'                  => ['nullable', 'string', 'alpha_dash', 'max:50', 'unique:users,name'],
            'first_name'            => ['nullable', 'string', 'max:50'],
            'last_name'             => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Override failed validation to return custom flat error payload.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'detail' => 'Invalid registration credentials provided.'
        ], Response::HTTP_BAD_REQUEST));
    }
}