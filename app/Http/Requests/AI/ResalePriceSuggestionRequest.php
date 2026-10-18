<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class ResalePriceSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days_remaining' => 'required|integer|min:1',
            'original_price' => 'required|numeric|min:0',
            'merchant_category' => 'nullable|string|max:64',
            'historical_demand_level' => 'nullable|string|max:32',
            'similar_listings_sold_count' => 'nullable|integer|min:0',
            'avg_days_to_sell' => 'nullable|numeric|min:0',
            'async' => 'nullable|boolean',
        ];
    }
}
