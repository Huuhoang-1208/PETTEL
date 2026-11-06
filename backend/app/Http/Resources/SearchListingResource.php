<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class SearchListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = PublicListingResource::make($this->resource)->resolve($request);
        $data['match_reasons'] = $this->resource->getAttribute('search_match_reasons') ?? [];

        return $data;
    }
}
