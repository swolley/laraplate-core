<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface che definisce le operazioni di analytics supportate dai motori di ricerca.
 */
interface ISearchAnalytics
{
    /**
     * Ottiene metriche basate sul tempo (es. distribuzione temporale).
     *
     * @param  Model  $model  Modello da analizzare
     * @param  array  $filters  Filtri da applicare
     * @param  string  $interval  Intervallo temporale (es. '1d', '1M')
     * @return array Risultati dell'aggregazione
     */
    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array;

    /**
     * Ottiene metriche basate su termini (es. frequenza di valori in un campo).
     *
     * @param  Model  $model  Modello da analizzare
     * @param  string  $field  Campo da aggregare
     * @param  array  $filters  Filtri da applicare
     * @param  int  $size  Numero massimo di bucket da restituire
     * @return array Risultati dell'aggregazione
     */
    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array;

    /**
     * Ottiene metriche basate su dati geografici.
     *
     * @param  Model  $model  Modello da analizzare
     * @param  string  $geoField  Campo geografico da utilizzare
     * @param  array  $filters  Filtri da applicare
     * @return array Risultati dell'aggregazione
     */
    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array;

    /**
     * Ottiene statistiche su un campo numerico.
     *
     * @param  Model  $model  Modello da analizzare
     * @param  string  $field  Campo da aggregare
     * @param  array  $filters  Filtri da applicare
     * @return array Risultati dell'aggregazione (min, max, avg, sum, ecc.)
     */
    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array;

    /**
     * Calcola la distribuzione di valori in intervalli (istogramma).
     *
     * @param  Model  $model  Modello da analizzare
     * @param  string  $field  Campo da aggregare
     * @param  array  $filters  Filtri da applicare
     * @param  mixed  $interval  Intervallo per i bucket
     * @return array Risultati dell'aggregazione
     */
    public function getHistogram(Model $model, string $field, array $filters = [], $interval = 50): array;
}
