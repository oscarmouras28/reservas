<?php

namespace App\Http\Requests;

use App\Support\Rut;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('document_type');
        $document = $this->input('document_number');

        if (is_string($document)) {
            if ($type === 'rut') {
                // Si no es válido se deja tal cual y la regla lo rechaza.
                $document = Rut::normalize($document) ?? trim($document);
            } else {
                $document = strtoupper((string) preg_replace('/\s+/', '', $document));
            }
            $this->merge(['document_number' => $document]);
        }

        $phone = $this->input('phone');
        if (is_string($phone)) {
            $this->merge(['phone' => (string) preg_replace('/[\s().-]/', '', $phone)]);
        }

        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => strtolower(trim($email))]);
        }
    }

    public function rules(): array
    {
        return [
            'service' => ['required', 'integer'],
            'start' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
            'name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\p{L}\p{M}'’. -]+$/u"],
            'document_type' => ['required', Rule::in(['rut', 'passport'])],
            'document_number' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value)) {
                    return;
                }
                if ($this->input('document_type') === 'rut') {
                    if (Rut::normalize($value) === null) {
                        $fail('El RUT no es válido. Revisa el número y el dígito verificador.');
                    }
                } elseif (! preg_match('/^[A-Z0-9]{5,20}$/', $value)) {
                    $fail('El pasaporte debe tener entre 5 y 20 letras o números.');
                }
            }],
            'phone' => ['required', 'string', 'regex:/^\+?\d{8,15}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'address' => ['required', 'string', 'min:5', 'max:200'],
            'privacy' => ['accepted'],
            'adult' => ['accepted'],
            'turnstile' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Completa el campo :attribute.',
            'min' => 'El campo :attribute es demasiado corto.',
            'max' => 'El campo :attribute es demasiado largo.',
            'name.regex' => 'El nombre solo puede tener letras, espacios, puntos, guiones y apóstrofes.',
            'phone.regex' => 'Escribe un teléfono válido, por ejemplo +56 9 1234 5678.',
            'email.email' => 'Escribe un correo válido.',
            'privacy.accepted' => 'Debes aceptar la política de privacidad.',
            'adult.accepted' => 'Debes declarar que eres mayor de 18 años.',
            'service.*' => 'Elige un tipo de sesión.',
            'start.*' => 'Elige una hora del calendario.',
            'document_type.*' => 'Elige RUT o pasaporte.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'document_number' => 'RUT o pasaporte',
            'phone' => 'teléfono',
            'email' => 'correo',
            'address' => 'dirección',
        ];
    }
}
