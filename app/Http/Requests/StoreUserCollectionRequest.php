<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            'name'       => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:users,name'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'   => [
                    'required', 
                    'string', 
                    Password::min(8)
                        ->letters()
                        ->mixedCase()   // Enforces both capital and small letters
                        ->numbers()     // Enforces numbers
                        ->symbols()     // Enforces special characters
                        ->uncompromised() // Direct weapon against dictionary attacks (checks leaked password databases)
                ],
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name'  => ['nullable', 'string', 'max:50'],
        ];
    }
}
