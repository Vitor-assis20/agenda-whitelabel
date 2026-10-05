<?php

namespace App\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class BrandingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Sem SVG (File::image() já recusa): pode carregar scripts e é servido do mesmo domínio.
            'logo' => [
                'nullable',
                File::image()->max(1024)->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)),
                'mimes:png,jpg,jpeg,webp',
            ],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do negócio.',
            'primary_color.*' => 'Escolha uma cor válida (ex.: #B45309).',
            'logo.image' => 'Envie uma imagem PNG, JPG ou WebP.',
            'logo.mimes' => 'Envie uma imagem PNG, JPG ou WebP.',
            'logo.max' => 'A imagem deve ter no máximo 1 MB.',
            'logo.dimensions' => 'A imagem deve ter pelo menos 64×64 pixels.',
        ];
    }
}
