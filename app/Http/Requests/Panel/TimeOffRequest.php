<?php

namespace App\Http\Requests\Panel;

use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimeOffRequest extends FormRequest
{
    /**
     * Folga mais longa aceita (férias longas cabem; erro de digitação no ano não).
     */
    public const MAX_DAYS = 90;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'professional_id' => [
                'required',
                'integer',
                Rule::exists('professionals', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
            'all_day' => ['required', 'boolean'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['exclude_unless:all_day,true', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['exclude_if:all_day,true', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_if:all_day,true', 'required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:100'],
            'confirm_conflicts' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'professional_id.*' => 'Escolha um profissional.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'end_time.after' => 'O fim deve ser depois do início.',
            '*.date_format' => 'Data ou horário inválido.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && $this->boolean('all_day')) {
                    $days = CarbonImmutable::parse($this->input('start_date'))->diffInDays($this->input('end_date')) + 1;

                    if ($days > self::MAX_DAYS) {
                        $validator->errors()->add('end_date', 'A folga pode ter no máximo '.self::MAX_DAYS.' dias.');
                    }
                }
            },
        ];
    }

    /**
     * Período em UTC, a partir das datas e horários locais do negócio.
     * Dia inteiro vai da meia-noite do primeiro dia à meia-noite após o último.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(string $timezone): array
    {
        if ($this->boolean('all_day')) {
            return [
                CarbonImmutable::parse($this->validated('start_date'), $timezone)->startOfDay()->utc(),
                CarbonImmutable::parse($this->validated('end_date'), $timezone)->addDay()->startOfDay()->utc(),
            ];
        }

        $date = $this->validated('start_date');

        return [
            CarbonImmutable::parse("{$date} {$this->validated('start_time')}", $timezone)->utc(),
            CarbonImmutable::parse("{$date} {$this->validated('end_time')}", $timezone)->utc(),
        ];
    }
}
