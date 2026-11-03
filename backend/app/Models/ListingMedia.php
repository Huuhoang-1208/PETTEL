<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property-read int $sort_order */
class ListingMedia extends Pivot
{
    protected $table = 'listing_media';
}
