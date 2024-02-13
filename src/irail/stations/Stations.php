<?php

/**
 * Copyright (C) 2011 by iRail vzw/asbl
 * Copyright (C) 2016 by Open Knowledge Belgium vzw/asbl.
 *
 * Basic functionalities needed for playing with Belgian railway stations in Belgium
 */

namespace irail\stations;

use stdClass;

class Stations extends StationsDataset
{
    private static string $stationsfilename = '/../../../stations.jsonld';
    private static object $stations;

    /**
     * Gets you stations in a JSON-LD graph ordered by relevance to the optional query.
     *
     * @param string $query
     * @param string $country shortcode for a country (e.g., be, de, fr...)
     * @return object a JSON-LD graph with context
     */
    public static function getStations(string $query = '', string $country = '', bool $sorted = false): object
    {
        if ($query == null || $query === '') {
            // Loading from the loadJsonLd method has a high chance of a cache hit, meaning decoding and disk IO isn't needed
            self::loadJsonLd();
            return clone self::$stations;
        }

        // Escape all special characters for PSR6-compliant key.
        $query_cache_key = 'Stations|' . preg_replace('/[^a-zA-Z0-9]/', '-', $query);

        // keep all function parameters in key, separate cache entry for every unique request.
        $apc_key = $query_cache_key . '|' . $country . '|' . $sorted;

        $cached = stationsCache::getFromCache($apc_key);
        if ($cached !== false) {
            return $cached;
        }

        // Only require the jsonLD file to be loaded when it's a cache miss
        self::loadJsonLd();

        // Filter the stations on name match
        $stations = self::$stations;
        $newstations = new stdClass();
        $newstations->{'@id'} = $stations->{'@id'} . '?q=' . $query;
        $newstations->{'@context'} = $stations->{'@context'};
        $newstations->{'@graph'} = [];

        $query = self::standardizeQuery($query);

        $query = self::normalizeAccents($query);
        // Dashes are the same as spaces
        $query = preg_replace("/([- ])+/", " ", $query);

        $count = 0;

        // Create a sorted list based on the vehicle_frequency
        $stations_array = $stations->{'@graph'};

        if ($sorted) {
            usort($stations_array, ['\irail\stations\StationsDataset', 'cmp_stationsld_vehicle_frequency']);
        }

        foreach ($stations_array as $station) {
            $testStationName = str_replace(' am ', ' ', self::normalizeAccents($station->{'name'}));
            $testStationName = preg_replace("/(-| )+/", " ", $testStationName);

            $exactMatch = false;
            $partialMatch = false;

            if (self::isEqualCaseInsensitive($query, $testStationName)) {
                // If this is a direct match for case insensitive search (with or without the apostrophe ' characters
                $exactMatch = true;
            } else {
                if (self::isQueryPartOfName($query, $testStationName)) {
                    // If this is a direct match for case insensitive search (with or without the apostrophe ' characters
                    $partialMatch = true;
                }

                // Even when we have a partial match, we should keep searching for an exact math
                if (isset($station->alternative)) {
                    // If this station in the list has an alternative form, try to match alternatives
                    foreach ($station->alternative as $alternative) {
                        $testStationName = str_replace(' am ', ' ', self::normalizeAccents($alternative->{'@value'}));
                        $testStationName = preg_replace("/(-| )+/", " ", $testStationName);

                        if (self::isEqualCaseInsensitive($query, $testStationName)) {
                            // If this is a direct match for case insensitive search (with or without the apostrophe ' characters
                            $exactMatch = true;
                            break;
                        }

                        // Don't try a partial match if we found it in an earlier version already!
                        if (self::isQueryPartOfName($query, $testStationName)) {
                            $partialMatch = true;
                        }
                    }
                }
            }

            if ($exactMatch) {
                self::arrayUnshiftUnique($newstations->{'@graph'}, $station);
                $count++;
            } elseif ($count <= 5 && $partialMatch) {
                // Max 6 results, but keep searching when not sorted to ensure we don't miss an exact match
                $newstations->{'@graph'}[] = $station;
                $count++;
            }

            // Don't keep searching after 5 results when sorted. Return.
            if ($sorted && $count > 5) {
                stationsCache::setCache($apc_key, $newstations);
                return $newstations;
            }
        }

        stationsCache::setCache($apc_key, $newstations);
        return $newstations;
    }

    /**
     * Gives an object for an id.
     *
     * @param $id int|string can be a URI, a hafas id or an old-style iRail id (BE.NMBS.{hafasid})
     *
     * @return Object a simple object for a station
     */
    public static function getStationFromID($id)
    {
        // Escape all special characters for PSR6-compliant key.
        $id_cache_key = 'Stations|' . preg_replace('/[^a-zA-Z0-9]/', '-', $id);

        $cached = stationsCache::getFromCache($id_cache_key);
        if ($cached !== false) {
            return $cached;
        }

        //transform the $id into a URI if it's not yet a URI
        if (!str_starts_with($id, 'http')) {
            //test for old-style iRail ids
            if (str_starts_with($id, 'BE.NMBS.')) {
                $id = substr($id, 8);
            }
            $id = 'http://irail.be/stations/NMBS/' . $id;
        }

        self::loadJsonLd();

        $stationsDocument = self::$stations;
        foreach ($stationsDocument->{'@graph'} as $station) {
            if ($station->{'@id'} === $id) {
                stationsCache::setCache($id_cache_key, $station);
                return $station;
            }
        }
        return null;
    }

    private static function loadJsonLd(): void
    {
        if (!isset(self::$stations)) {
            // try to load from cache. If not availabe, load from file.
            $ld_key = 'ld';
            $cached = stationsCache::getFromCache($ld_key);

            if ($cached !== false) {
                self::$stations = $cached;
            } else {
                self::$stations = json_decode(file_get_contents(__DIR__ . self::$stationsfilename));
                stationsCache::setCache($ld_key, self::$stations);
            }
        }
    }

}
