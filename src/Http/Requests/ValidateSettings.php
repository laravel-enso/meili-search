<?php

namespace LaravelEnso\MeiliSearch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class ValidateSettings extends FormRequest
{
    public function rules()
    {
        return [
            'master_key' => ['string', 'nullable', 'max:255', $this->required()],
            'host' => 'url|max:255|required_if:enabled,true',
            'enabled' => 'required|boolean',
        ];
    }

    private function required()
    {
        $required = App::isProduction()
            && $this->get('enabled')
            && ! $this->route('settings')->configured();

        return Rule::requiredIf($required);
    }
}
