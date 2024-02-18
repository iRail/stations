<?php

/**
 * Copyright (C) 2011 by iRail vzw/asbl
 * Copyright (C) 2016 by Open Knowledge Belgium vzw/asbl.
 *
 * Basic functionalities needed for playing with Belgian railway stations in Belgium
 */

namespace irail\stations;

class Stations extends StationsDataset
{
    const STATION_SEARCH_RESULT_COUNT = 5;

    private static string $stationsfilename = '/../../../stations.csv';

    /**
     * @var Station[]
     */
    private static array $stations;

    /**
     * Gets you stations in an array ordered by relevance to the optional query.
     *
     * @param string $query
     * @param string $country shortcode for a country (e.g., be, de, fr...)
     * @return Station[] a JSON-LD graph with context
     */
    public static function getStations(string $query = '', string $country = ''): array
    {
        if ($query === null || $query === '') {
            // Loading from the loadCsv method has a high chance of a cache hit, meaning decoding and disk IO isn't needed
            self::loadCsv();
            return self::$stations;
        }

        // Escape all special characters for PSR6-compliant key.
        $query_cache_key = preg_replace('/[^a-zA-Z0-9]/', '-', $query);
        // keep all function parameters in key, separate cache entry for every unique request.
        $cache_key = 'Stations|' . $query_cache_key . '|' . $country;

        $cached = StationsCache::getFromCache($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Only require the csv file to be loaded when it's a cache miss
        self::loadCsv();

        // Filter the stations on name match
        $allStations = self::$stations;
        $query = self::standardizeQuery($query);

        $query = self::normalizeAccents($query);
        // Dashes are the same as spaces
        $query = preg_replace("/([- ])+/", " ", $query);

        $count = 0;

        $resultStations = []; // The result array which will be filled as soon as matches are found

        foreach ($allStations as $station) {
            if ($country && $station->getCountryCode() != $country) {
                continue;
            }
            $exactMatch = false;
            $partialMatch = false;

            $localizedNames = $station->getLocalizedNames();
            array_unshift($localizedNames, $station->getName());
            $allNames = array_unique($localizedNames);

            // If this station in the list has an alternative form, try to match alternatives
            foreach ($allNames as $name) {
                $testStationName = str_replace(' am ', ' ', self::normalizeAccents($name));
                $testStationName = preg_replace("/([- ])+/", " ", $testStationName);

                if (self::isEqualCaseInsensitive($query, $testStationName)) {
                    // If this is a direct match for case-insensitive search (with or without the apostrophe ' characters
                    $exactMatch = true;
                    break;
                }

                // Don't try a partial match if we found it in an earlier version already!
                if (self::isQueryPartOfName($query, $testStationName)) {
                    $partialMatch = true;
                }
            }

            if ($exactMatch) {
                self::arrayUnshiftUnique($resultStations, $station);
                $count++;
            } elseif ($count <= self::STATION_SEARCH_RESULT_COUNT && $partialMatch) {
                // Max 6 results, but keep searching when not sorted to ensure we don't miss an exact match
                $resultStations[] = $station;
                $count++;
            }

            // Don't keep searching after 5 results since the results are already sorted on station size. Return.
            if ($count > self::STATION_SEARCH_RESULT_COUNT) {
                StationsCache::setCache($cache_key, $resultStations);
                return $resultStations;
            }
        }

        StationsCache::setCache($cache_key, $resultStations);
        return $resultStations;
    }

    /**
     * Gives an object for an id.
     *
     * @param $id int|string can be a URI, a HAFAS id (7-digit number) or an old-style iRail id (BE.NMBS.{hafasid})
     *
     * @return Station|null a simple object for a station
     */
    public static function getStationFromID($id): ?Station
    {
        // Escape all special characters for PSR6-compliant key.
        $id_cache_key = 'Stations|' . preg_replace('/[^a-zA-Z0-9]/', '-', $id);
        $cached = StationsCache::getFromCache($id_cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $id = strtolower(self::idToIrailUri($id));

        self::loadCsv();

        $allStations = self::$stations;
        if (key_exists($id, $allStations)) {
            return $allStations[$id];
        }
        return null;
    }

    private static function loadCsv(): void
    {
        if (!isset(self::$stations)) {
            // try to load from cache. If not available, load from file.
            $csv_cache_key = 'csv';
            $cached = StationsCache::getFromCache($csv_cache_key);

            if ($cached !== false) {
                self::$stations = $cached;
            } else {
                self::$stations = StationsCsvParser::parse(__DIR__ . self::$stationsfilename);
                // Sort before caching, so it only needs to be performed once
                uasort(self::$stations, ['\irail\stations\StationsDataset', 'cmp_stations_vehicle_frequency']);
                StationsCache::setCache($csv_cache_key, self::$stations);
            }
        }
    }

}
