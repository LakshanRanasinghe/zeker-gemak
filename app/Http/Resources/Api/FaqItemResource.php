<?php

namespace App\Http\Resources\Api;

use App\Support\LocalizedModelValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single reusable FAQ question & answer, localized to the request locale.
 */
class FaqItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => LocalizedModelValue::string($this->resource, 'question', $this->resource->question),
            'answer' => LocalizedModelValue::string($this->resource, 'answer', $this->resource->answer),
        ];
    }
}
