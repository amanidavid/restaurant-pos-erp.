<?php

namespace App\Http\Resources\Web\Foundation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'legal_name' => $this->legal_name,
            'trading_name' => $this->trading_name,
            'tin' => $this->tin,
            'vrn' => $this->vrn,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'base_currency_code' => $this->base_currency_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
        ];
    }
}
