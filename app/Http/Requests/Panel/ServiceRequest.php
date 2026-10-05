<?php

namespace App\Http\Requests\Panel;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('services')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->ignore($this->route('service')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do serviço.',
            'name.unique' => 'Já existe um serviço com este nome.',
            'duration_minutes.*' => 'A duração deve ser de 5 a 480 minutos, em múltiplos de 5.',
            'price_cents.*' => 'Informe um preço válido.',
        ];
    }
}
