<?php

namespace App\Http\Requests\Booking;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', $this->existsInTenant('services')],
            'professional_id' => ['required', 'integer', $this->existsInTenant('professionals')],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Preencha este campo.',
            'email' => 'Informe um e-mail válido.',
            'exists' => 'Opção indisponível. Recarregue a página.',
            'max' => 'Texto muito longo.',
        ];
    }

    private function existsInTenant(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where('tenant_id', app(CurrentTenant::class)->id())
            ->where('is_active', true);
    }
}
