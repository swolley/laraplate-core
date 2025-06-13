<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface defining the analytics operations supported by search engines.
 */
interface ISearchAnalytics
{
    /**
     * It gets time-based metrics (e.g., time distribution).
     *
     * @param  Model  $model  Model to analyze
     * @param  array  $filters  Filters to apply
     * @param  string  $interval  Time interval (e.g., '1d', '1M')
     * @return array Aggregation results
     */
    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array;

    /**
     * It gets metrics based on terms (e.g., frequency of values in a field).
     *
     * @param  Model  $model  Model to analyze
     * @param  string  $field  Field to aggregate
     * @param  array  $filters  Filters to apply
     * @param  int  $size  Maximum number of buckets to return
     * @return array Aggregation results
     */
    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array;

    /**
     * It gets metrics based on geographic data.
     *
     * @param  Model  $model  Model to analyze
     * @param  string  $geoField  Geographic area to use
     * @param  array  $filters  Filters to apply
     * @return array Aggregation results
     */
    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array;

    /**
     * It gets statistics on a numeric field.
     *
     * @param  Model  $model  Model to analyze
     * @param  string  $field  Field to aggregate
     * @param  array  $filters  Filters to apply
     * @return array Aggregation results (min, max, avg, sum, etc.)
     */
    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array;

    /**
     * Calculate the distribution of values in intervals (histogram).
     *
     * @param  Model  $model  Model to analyze
     * @param  string  $field  Field to aggregate
     * @param  array  $filters  Filters to apply
     * @param  mixed  $interval  Bucket range
     * @return array Aggregation results
     */
    public function getHistogram(Model $model, string $field, array $filters = [], int $interval = 50): array;
}
