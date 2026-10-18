<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class ChurnPredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login_frequency' => 'nullable|numeric|min:0',
            'days_since_last_login' => 'nullable|integer|min:0',
            'feature_usage_count' => 'nullable|integer|min:0',
            'previous_second_chance_usage' => 'nullable|integer|min:0',
            'subscription_tenure' => 'nullable|integer|min:0',
            'avg_session_duration' => 'nullable|numeric|min:0',
            'async' => 'nullable|boolean',
        ];
    }
}
