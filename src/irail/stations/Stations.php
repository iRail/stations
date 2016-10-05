<?php

/**
 * Copyright (C) 2011 by iRail vzw/asbl
 * Copyright (C) 2016 by Open Knowledge Belgium vzw/asbl.
 *
 * Basic functionalities needed for playing with Belgian railway stations in Belgium
 */
namespace irail\stations;

class Stations
{
    private static $stationsfilename = '/../../../stations.jsonld';
    private static $stations;

    /**
     * Gets you stations in a JSON-LD graph ordered by relevance to the optional query.
     *
     * @todo would we be able to implement this with an in-mem store instead of reading from the file each time?
     *
     * @param string $query
     *
     * @todo @param string country shortcode for a country (e.g., be, de, fr...)
     *
     * @return object a JSON-LD graph with context
     */
    public static function getStations($query = '', $country = '', $sorted = false)
    {
        if (!isset(self::$stations)) {
            self::$stations = json_decode(file_get_contents(__DIR__.self::$stationsfilename));
        }
        if ($query && $query !== '') {
            // Filter the stations on name match
            $stations = self::$stations;
            $newstations = new \stdClass();
            $newstations->{'@id'} = $stations->{'@id'}.'?q='.$query;
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
            $query = str_replace("\-", "[\- ]", $query);
            $query = str_replace(' ', "[\- ]", $query);

            $count = 0;

            // Create a sorted list based on the vehicle_frequency
            $stations_array = $stations->{'@graph'};

            if ($sorted) {
                usort($stations_array, ['\irail\stations\Stations', 'cmp_stations_vehicle_frequency']);
            }

            foreach ($stations_array as $station) {
                $testStationName = str_replace(' am ', ' ', self::normalizeAccents($station->{'name'}));
                if (preg_match('/.*'.$query.'.*/i', $testStationName, $match)
                    || preg_match('/.*'.$query.'.*/i', str_replace('\'', ' ', $testStationName), $match)) {
                    $newstations->{'@graph'}[] = $station;
                    $count++;
                } elseif (isset($station->alternative)) {
                    if (is_array($station->alternative)) {
                        foreach ($station->alternative as $alternative) {
                            $testStationName = str_replace(' am ', ' ', self::normalizeAccents($alternative->{'@value'}));
                            if (preg_match('/.*('.$query.').*/i', $testStationName, $match)
                                || preg_match('/.*('.$query.').*/i', str_replace('\'', ' ', $testStationName), $match)) {
                                $newstations->{'@graph'}[] = $station;
                                $count++;
                                break;
                            }
                        }
                    } else {
                        $testStationName = str_replace(' am ', ' ', self::normalizeAccents($alternative->{'@value'}));
                        if (preg_match('/.*'.$query.'.*/i', $testStationName)
                            || preg_match('/.*('.$query.').*/i', str_replace('\'', ' ', $testStationName), $match)) {
                            $newstations->{'@graph'}[] = $station;
                            $count++;
                        }
                    }
                }
                if ($count > 5) {
                    return $newstations;
                }
            }

            return $newstations;
        } else {
            return json_decode(file_get_contents(__DIR__.self::$stationsfilename));
        }
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
     * @param $str
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
            'ÿ' => 'y',
        ];

        return strtr($str, $unwanted_array);
    }

    /**
     * Gives an object for an id.
     *
     * @param $id can be a URI, a hafas id or an old-style iRail id (BE.NMBS.{hafasid})
     *
     * @return a simple object for a station
     */
    public static function getStationFromID($id)
    {
        //transform the $id into a URI if it's not yet a URI
        if (substr($id, 0, 4) !== 'http') {
            //test for old-style iRail ids
            if (substr($id, 0, 8) === 'BE.NMBS.') {
                $id = substr($id, 8);
            }
            $id = 'http://irail.be/stations/NMBS/'.$id;
        }

        if (!isset(self::$stations)) {
            self::$stations = json_decode(file_get_contents(__DIR__.self::$stationsfilename));
        }

        $stationsdocument = self::$stations;

        foreach ($stationsdocument->{'@graph'} as $station) {
            if ($station->{'@id'} === $id) {
                return $station;
            }
        }
    }
}
