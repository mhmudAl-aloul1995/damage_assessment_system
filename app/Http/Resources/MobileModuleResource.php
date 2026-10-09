<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'title' => __($this->resource['title']),
            'description' => __($this->resource['description'] ?? ''),
            'icon' => $this->resource['icon'] ?? null,
            'sections' => collect($this->resource['sections'])->map(fn (array $section): array => [
                'title' => __($section['title']),
                'icon' => $section['icon'] ?? null,
            ])->values()->all(),
        ];
    }
}
