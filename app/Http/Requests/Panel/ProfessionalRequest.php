<?php

namespace App\Http\Requests\Panel;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfessionalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => [
                'integer',
                Rule::exists('services', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do profissional.',
            'email.email' => 'Informe um e-mail válido.',
            'service_ids.*.exists' => 'Serviço inválido.',
        ];
    }
}
