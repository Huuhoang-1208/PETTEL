<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Message extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['sender_id', 'body'];

    protected $attributes = ['visibility' => 'VISIBLE'];

    /** @return BelongsToMany<MediaObject, $this> */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(MediaObject::class, 'message_attachments')
            ->where('media_objects.visibility', 'PRIVATE')
            ->where('media_objects.state', 'READY')
            ->orderBy('media_objects.id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        // Keep all six fractional digits when Laravel encodes a pagination cursor.
        return ['created_at' => 'string'];
    }
}
