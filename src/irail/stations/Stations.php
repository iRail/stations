<?php

/**
 * Copyright (C) 2011 by iRail vzw/asbl
 * Copyright (C) 2016 by Open Knowledge Belgium vzw/asbl.
 *
 * Basic functionalities needed for playing with Belgian railway stations in Belgium
 */

namespace irail\stations;

use Cache\Adapter\Apc\ApcCachePool;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;

class Stations
{
    private static $stationsfilename = '/../../../stations.jsonld';
    private static $stations;

    /**
     * @var $cache AbstractCachePool
     */
    private static $cache;

    const APC_PREFIX = "|Irail|Stations|";
    const APC_TTL = 0; // Store forever (or until restart). Cache can be manually cleared too.

    /**
     * Create a cache pool if it does not exists.
     * @return \Cache\Adapter\Apc\ApcCachePool|\Cache\Adapter\Common\AbstractCachePool The cache pool
     */
    public static function createCachePool()
    {
        if (self::$cache == null) {
            // Try to use APC when available
            if (extension_loaded('apc')) {
                self::$cache = new ApcCachePool();
            } else {
                // Fall back to array cache
                self::$cache = new ArrayCachePool();
            }
        }

        return self::$cache;
    }

    /**
     * Get an item from the cache.
     *
     * @param String $key The key to search for.
     * @return bool|object The cached object if found. If not found, false.
     */
    private static function getFromCache($key)
    {
        self::createCachePool();

        if (self::$cache->hasItem($key)) {
            return self::$cache->getItem($key)->get();
        } else {
            return false;
        }
    }

    /**
     * Store an object in cache
     *
     * @param string $key The key identifier for this object
     * @param object $value The object to store
     * @param int    $ttl How long this item should be kept in cache
     */
    private static function setCache($key, $value, $ttl = 0)
    {
        self::createCachePool();

        $item = self::$cache->getItem($key);

        $item->set($value);
        if ($ttl > 0) {
            $item->expiresAfter($ttl);
        }

        self::$cache->save($item);
    }

    private static function loadJsonLd()
    {
        if (!isset(self::$stations)) {
            // try to load from cache. If not availabe, load from file.
            $csv_key = self::APC_PREFIX . 'csv';
            $cached = self::getFromCache($csv_key);

            if ($cached !== false) {
                self::$stations = $cached;
            } else {
                self::$stations = json_decode(file_get_contents(__DIR__ . self::$stationsfilename));
                self::setCache($csv_key, self::$stations);
            }
        }
    }

    /**
     * Gets you stations in a JSON-LD graph ordered by relevance to the optional query.
     *
     * @param string $query
     *
     * @todo @param string country shortcode for a country (e.g., be, de, fr...)
     *
     * @return object a JSON-LD graph with context
     */
    public static function getStations($query = '', $country = '', $sorted = false)
    {
        if ($query == null || $query === '') {
            // Loading from the loadJsonLd method has a high chance of a cache hit, meaning decoding and disk IO isn't needed
            self::loadJsonLd();
            return clone self::$stations;
        }

        // Escape all special characters for PSR6-compliant key.
        $query_cache_key = preg_replace('/[^a-zA-Z0-9]/', '-', $query);

        // keep all function parameters in key, separate cache entry for every unique request.
        $apc_key = self::APC_PREFIX . $query_cache_key . '|' . $country . '|' . $sorted;

        $cached = self::getFromCache($apc_key);
        if ($cached !== false) {
            return $cached;
        }

        // Only require the jsonLD file to be loaded when it's a cache miss
        self::loadJsonLd();

        // Filter the stations on name match
        $stations = self::$stations;
        $newstations = new \stdClass();
        $newstations->{'@id'} = $stations->{'@id'} . '?q=' . $query;
        $newstations->{'@context'} = $stations->{'@context'};
        $newstations->{'@graph'} = [];

        //https://github.com/iRail/stations/issues/101
        $query = preg_replace('/Brussel Nat.+/', 'Brussels Airport', $query);
        $query = preg_replace('/Brussels Airport ?-? ?Z?a?v?e?n?t?e?m?/', 'Brussels Airport', $query);

        //https://github.com/iRail/stations/issues/72
        $query = str_ireplace('- ', '-', $query);

        //https://github.com/iRail/hyperRail/issues/129
        $query = str_ireplace('l alleud', "l'alleud", $query);

        //https://github.com/iRail/iRail/issues/165
        $query = str_ireplace(' Cdg ', ' Charles de Gaulle ', $query);

        $query = str_ireplace(' am ', ' ', $query);
        $query = str_ireplace('frankfurt fl', 'frankfurt main fl', $query);

        //https://github.com/iRail/iRail/issues/66
        $query = str_ireplace('Bru.', 'Brussel', $query);
        //https://github.com/iRail/iRail/issues/137
        $query = str_ireplace('Brux.', 'Bruxelles', $query);
        //make sure something between brackets is ignored
        $query = preg_replace("/\s?\(.*?\)/i", '', $query);

        // st. is the same as Saint
        $query = str_ireplace('st-', 'st ', $query);
        $query = str_ireplace('st.-', 'st ', $query);
        $query = preg_replace("/st(\s|$|\.)/i", '(saint|st|sint) ', $query);
        //make sure that we're only taking the first part before a /
        $query = explode('/', $query);
        $query = trim($query[0]);

        // Dashes are the same as spaces
        $query = self::normalizeAccents($query);
        $query = preg_replace("/(-| )+/", " ", $query);

        $count = 0;

        // Create a sorted list based on the vehicle_frequency
        $stations_array = $stations->{'@graph'};

        if ($sorted) {
            usort($stations_array, ['\irail\stations\Stations', 'cmp_stations_vehicle_frequency']);
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
                self::arrayPutValueFirst($newstations->{'@graph'}, $station);
                $count++;
            } else if ($count <= 5 && $partialMatch) {
                // Max 6 results, but keep searching when not sorted to ensure we don't miss an exact match
                $newstations->{'@graph'}[] = $station;
                $count++;
            }

            // Don't keep searching after 5 results when sorted. Return.
            if ($sorted && $count > 5) {
                self::setCache($apc_key, $newstations, self::APC_TTL);
                return $newstations;
            }
        }

        self::setCache($apc_key, $newstations, self::APC_TTL);
        return $newstations;
    }

    /**
     * Put an array value first. If the array contains the value already, move the value to the first place.
     * @param $array array The array in which the value should be stored
     * @param $value mixed The value to store
     */
    private static function arrayPutValueFirst(&$array, $value)
    {
        if (($key = array_search($value, $array)) !== false) {
            unset($array[$key]);
        }
        array_unshift($array, $value);
    }

    /**
     * Check if a query is part of a station name. Case insensitive
     * @param $query String the query we're looking for
     * @param $testStationName String The station name which might contain query
     * @return bool True if $query is in $testStationName
     */
    private static function isQueryPartOfName($query, $testStationName)
    {
        return preg_match('/.*(' . $query . ').*/i', $testStationName, $match)
            || preg_match('/.*(' . $query . ').*/i', str_replace('\'', ' ', $testStationName), $match);
    }

    /**
     * Check if a query equals a string, case and apostrophe insensitive
     * @param $query String the query we're looking for
     * @param $testStationName String The station name which might be equal to query
     * @return bool True if $query is equal to $testStationName, except possible casing or apostrophes
     */
    private static function isEqualCaseInsensitive($query, $testStationName)
    {
        return preg_match('/^' . $query . '$/i', $testStationName, $match)
            || preg_match('/^' . $query . '$/i', str_replace('\'', ' ', $testStationName), $match);
    }

    /**
     * Compare 2 stations based on vehicle frequency.
     *
     * @param $a \stdClass the first station
     * @param $b \stdClass the second station
     *
     * @return int The result of the compare. 0 if equal, -1 if a is after b, 1 if b is before a
     */
    public static function cmp_stations_vehicle_frequency($a, $b)
    {
        if ($a == $b) {
            return 0;
        }
        //sort sorts from low to high, so lower avgStopTimes will result in a higher ranking.
        return ($a->avgStopTimes < $b->avgStopTimes) ? 1 : -1;
    }

    /**
     * @param string $str
     *
     * @return string
     *                Languages supported are: German, French and Dutch
     *                We have to take into account that some words may have accents
     *                Taken from https://stackoverflow.com/questions/3371697/replacing-accented-characters-php
     */
    private static function normalizeAccents($str)
    {
        $unwanted_array = [
            'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
            'Þ' => 'Th', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a',
            'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ý' => 'y', 'þ' => 'th', 'œ' => 'oe',
            'ÿ' => 'y', 'ü' => 'u', 'Đ' => 'Dj', 'đ' => 'dj',
            'Č' => 'C', 'č' => 'c', 'Ć' => 'C', 'ć' => 'c',
            'Ŕ' => 'R', 'ŕ' => 'r',
        ];

        return strtr($str, $unwanted_array);
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
        $id_cache_key = preg_replace('/[^a-zA-Z0-9]/', '-', $id);
        $apc_key = self::APC_PREFIX . $id_cache_key;

        $cached = self::getFromCache($apc_key);
        if ($cached !== false) {
            return $cached;
        }

        //transform the $id into a URI if it's not yet a URI
        if (substr($id, 0, 4) !== 'http') {
            //test for old-style iRail ids
            if (substr($id, 0, 8) === 'BE.NMBS.') {
                $id = substr($id, 8);
            }
            $id = 'http://irail.be/stations/NMBS/' . $id;
        }

        self::loadJsonLd();

        $stationsdocument = self::$stations;

        foreach ($stationsdocument->{'@graph'} as $station) {
            if ($station->{'@id'} === $id) {

                self::setCache($apc_key, $station, self::APC_TTL);

                return $station;
            }
        }
        return null;
    }


}
