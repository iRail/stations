<?php

namespace irail\stations;

/**
 * A base class for working with iRail stations data, used by the strongly typed StationsCsv facade and the linked-data Stations facade.
 */
abstract class StationsDataset
{
    /**
     * @param string $query
     * @return string
     */
    protected static function standardizeQuery(string $query): string
    {
        // https://github.com/iRail/stations/issues/101
        $query = preg_replace('/Brussel Nat.+/', 'Brussels Airport', $query);
        $query = preg_replace('/Brussels Airport ?-? ?Z?a?v?e?n?t?e?m?/', 'Brussels Airport', $query);

        // https://github.com/iRail/stations/issues/72
        $query = str_ireplace('- ', '-', $query);

        // https://github.com/iRail/hyperRail/issues/129
        $query = str_ireplace('l alleud', "l'alleud", $query);

        // https://github.com/iRail/iRail/issues/165
        $query = str_ireplace(' Cdg ', ' Charles de Gaulle ', $query);

        $query = str_ireplace(' am ', ' ', $query);
        $query = str_ireplace('frankfurt fl', 'frankfurt main fl', $query);

        // https://github.com/iRail/iRail/issues/66
        $query = str_ireplace('Bru.', 'Brussel', $query);

        // https://github.com/iRail/iRail/issues/137
        $query = str_ireplace('Brux.', 'Bruxelles', $query);

        // https://github.com/iRail/stations/154
        $query = str_ireplace('Maastricht Randwijck', 'Maastricht Randwyck', $query);

        //make sure something between brackets is ignored
        $query = preg_replace("/\s?\(.*?\)/i", '', $query);

        // st. is the same as Saint
        $query = str_ireplace('st-', 'st ', $query);
        $query = str_ireplace('st.-', 'st ', $query);
        $query = preg_replace("/st(\s|$|\.)/i", '(saint|st|sint) ', $query);
        //make sure that we're only taking the first part before a /
        $query = explode('/', $query);
        $query = trim($query[0]);
        return $query;
    }

    /**
     * Put an array value first. If the array contains the value already, move the value to the first place.
     * @param $array array The array in which the value should be stored
     * @param $value mixed The value to store
     */
    protected static function arrayUnshiftUnique(array &$array, mixed $value)
    {
        if (($key = array_search($value, $array)) !== false) {
            unset($array[$key]);
        }
        array_unshift($array, $value);
    }

    /**
     * Check if a query is part of a station name. Case-insensitive.
     * @param $query String the query we're looking for
     * @param $testStationName String The station name which might contain query
     * @return bool True if $query is in $testStationName
     */
    protected static function isQueryPartOfName(string $query, string $testStationName): bool
    {
        return preg_match('/' . $query . '/i', $testStationName)
            || preg_match('/(' . $query . ')/i', str_replace('\'', ' ', $testStationName), $match);
    }

    /**
     * Check if a query equals a string, case and apostrophe insensitive
     * @param $query String the query we're looking for
     * @param $testStationName String The station name which might be equal to query
     * @return bool True if $query is equal to $testStationName, except possible casing or apostrophes
     */
    protected static function isEqualCaseInsensitive(string $query, string $testStationName): bool
    {
        return preg_match('/^' . $query . '$/i', $testStationName)
            || preg_match('/^(' . $query . ')$/i', str_replace('\'', ' ', $testStationName), $match);
    }

    /**
     * Compare 2 stations based on vehicle frequency.
     *
     * @param Station $a stdClass the first station
     * @param Station $b stdClass the second station
     *
     * @return int The result of the compare. 0 if equal, -1 if a is after b, 1 if b is before a
     */
    protected static function cmp_stations_vehicle_frequency(Station $a, Station $b): int
    {
        if ($a->getAvgStopTimes() == $b->getAvgStopTimes()) {
            return 0;
        }
        //sort sorts from low to high, so lower avgStopTimes will result in a higher ranking.
        return ($a->getAvgStopTimes() < $b->getAvgStopTimes()) ? 1 : -1;
    }

    /**
     * Compare 2 stations based on vehicle frequency. Identical to cmp_stations_vehicle_frequency, but not strongly typed and with the correct field name
     * to fit the jsonld data.
     *
     * @param \stdClass $a stdClass the first station
     * @param \stdClass $b stdClass the second station
     * @return int The result of the compare. 0 if equal, -1 if a is after b, 1 if b is before a
     * @deprecated When possible, cmp_stations_vehicle_frequency should be used instead.
     */
    protected static function cmp_stationsld_vehicle_frequency(\stdClass $a, \stdClass $b): int
    {
        if ($a->avgStopTimes == $b->avgStopTimes) {
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
    protected static function normalizeAccents(string $str): string
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
     * @param int|string $id
     * @return int|string
     */
    public static function idToIrailUri(int|string $id): string|int
    {
        //transform the $id into a URI if it's not yet a URI
        if (!str_starts_with($id, 'http')) {
            //test for old-style iRail ids
            if (str_starts_with($id, 'BE.NMBS.')) {
                $id = substr($id, 8);
            }
            $id = 'http://irail.be/stations/NMBS/' . $id;
        }
        return $id;
    }
}