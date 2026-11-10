<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

final class PublicReviewCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $loader
     * @return T
     */
    public static function remember(string $listingId, int $page, int $perPage, Closure $loader): mixed
    {
        $version = (int) Cache::get(self::versionKey($listingId), 0);
        $key = "public-review-page:{$listingId}:v{$version}:p{$page}:pp{$perPage}";
        $cached = Cache::get($key);

        if ($cached !== null) {
            PerformanceQueryRecorder::recordCacheHit();

            return $cached;
        }

        PerformanceQueryRecorder::recordCacheMiss();

        return Cache::remember($key, now()->addMinutes(5), $loader);
    }

    public static function invalidate(string $listingId): void
    {
        $key = self::versionKey($listingId);
        Cache::add($key, 0);
        Cache::increment($key);
    }

    private static function versionKey(string $listingId): string
    {
        return "public-review-version:{$listingId}";
    }
}
