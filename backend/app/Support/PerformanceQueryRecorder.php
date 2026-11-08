<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;

final class PerformanceQueryRecorder
{
    /** @var array<string, array{count: int, total_time_ms: float, max_time_ms: float}> */
    private static array $sqlTemplates = [];

    private static int $queryCount = 0;

    private static float $queryTimeMs = 0.0;

    private static int $cacheHits = 0;

    private static int $cacheMisses = 0;

    public static function startRequest(): void
    {
        self::$sqlTemplates = [];
        self::$queryCount = 0;
        self::$queryTimeMs = 0.0;
        self::$cacheHits = 0;
        self::$cacheMisses = 0;
    }

    public static function record(QueryExecuted $query): void
    {
        self::$queryCount++;
        self::$queryTimeMs += $query->time;
        $template = self::$sqlTemplates[$query->sql] ?? [
            'count' => 0,
            'total_time_ms' => 0.0,
            'max_time_ms' => 0.0,
        ];
        $template['count']++;
        $template['total_time_ms'] += $query->time;
        $template['max_time_ms'] = max($template['max_time_ms'], $query->time);
        self::$sqlTemplates[$query->sql] = $template;
    }

    public static function recordCacheHit(): void
    {
        self::$cacheHits++;
    }

    public static function recordCacheMiss(): void
    {
        self::$cacheMisses++;
    }

    /**
     * @return array{
     *     query_count: int,
     *     query_time_ms: float,
     *     cache_hits: int,
     *     cache_misses: int,
     *     sql_templates: list<array{sql: string, count: int, total_time_ms: float, max_time_ms: float}>
     * }
     */
    public static function snapshot(): array
    {
        return [
            'query_count' => self::$queryCount,
            'query_time_ms' => round(self::$queryTimeMs, 3),
            'cache_hits' => self::$cacheHits,
            'cache_misses' => self::$cacheMisses,
            'sql_templates' => array_map(
                static fn (string $sql, array $metrics): array => [
                    'sql' => $sql,
                    'count' => $metrics['count'],
                    'total_time_ms' => round($metrics['total_time_ms'], 3),
                    'max_time_ms' => round($metrics['max_time_ms'], 3),
                ],
                array_keys(self::$sqlTemplates),
                array_values(self::$sqlTemplates),
            ),
        ];
    }
}
