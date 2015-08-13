<?php
/** 
 * Copyright (C) 2011 by iRail vzw/asbl
 * Copyright (C) 2015 by Open Knowledge Belgium vzw/asbl
 *
 * Basic functionalities needed for playing with Belgian railway stations in Belgium
 */
namespace irail\stations;

class Stations
{
    private static $stationsfilename = '/../../../stations.jsonld';
    
    /**
     * Gets you stations in a JSON-LD graph ordered by relevance to the optional query
     * @todo would we be able to implement this with an in-mem store instead of reading from the file each time?
     * @param string $query
     * @todo @param string country shortcode for a country (e.g., be, de, fr...)
     * @return object a JSON-LD graph with context
     */
    public static function getStations($query = "", $country = "")
    {
        
        if ($query && $query !== "") {
            // Filter the stations on name match
            $stations = json_decode(file_get_contents(__DIR__ . self::$stationsfilename));
            $newstations = new \stdClass;
            $newstations->{"@id"} = $stations->{"@id"} . "?q=" . $query;
            $newstations->{"@context"} = $stations->{"@context"};
            $newstations->{"@graph"} = array();

            //https://github.com/iRail/iRail/issues/66
            $query = str_replace(" am "," ", $query);
            
            //https://github.com/iRail/iRail/issues/66
            $query = str_replace("Bru.","Brussel", $query);
            //make sure something between brackets is ignored
            $query = preg_replace("/\s?\(.*?\)/i", "", $query);
            
            // st. is the same as Saint
            $query = preg_replace("/st(\s|$)/i", "(saint|st|sint) ", $query);
            //make sure that we're only taking the first part before a /
            $query = explode("/", $query);
            $query = trim($query[0]);
            
            // Dashes are the same as spaces
            $query = self::normalizeAccents($query);
            $query = str_replace("\-", "[\- ]", $query);
            $query = str_replace(" ", "[\- ]", $query);
            
            $count = 0;
            foreach ($stations->{"@graph"} as $station) {
                if (preg_match('/.*' . $query . '.*/i', str_replace(" am "," ",self::normalizeAccents($station->{"name"})), $match)) {
                    $newstations->{"@graph"}[] = $station;
                    $count++;
                } elseif (isset($station->alternative)) {
                    if (is_array($station->alternative)) {
                        foreach ($station->alternative as $alternative) {
                            if (preg_match('/.*(' . $query . ').*/i', str_replace(" am "," ",self::normalizeAccents($alternative->{"@value"})), $match)) {
                                $newstations->{"@graph"}[] = $station;
                                $count++;
                                break;
                            }
                        }
                    } else {
                        if (preg_match('/.*' . $query . '.*/i', str_replace(" am "," ",self::normalizeAccents($alternative->{"@value"})))) {
                            $newstations->{"@graph"}[] = $station;
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
            return json_decode(file_get_contents(__DIR__ . self::$stationsfilename));
        }
    }

    /**
     * @param $str
     * @return string
     * Languages supported are: German, French and Dutch
     * We have to take into account that some words may have accents
     * Taken from https://stackoverflow.com/questions/3371697/replacing-accented-characters-php
     */
    private static function normalizeAccents($str)
    {
        $unwanted_array = array(
            'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
            'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a',
            'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ý' => 'y', 'ý' => 'y', 'þ' => 'b',
            'ÿ' => 'y'
        );
        
        return strtr($str, $unwanted_array);
    }

    /**
     * Gives an object for an id
     * @param $id can be a URI, a hafas id or an old-style iRail id (BE.NMBS.{hafasid})
     * @return a simple object for a station
     */
    public static function getStationFromID($id){
        //transform the $id into a URI if it's not yet a URI
        if (substr($id,0,4) !== "http") {
            //test for old-style iRail ids
            if (substr($id,0,8) === "BE.NMBS.") {
                $id = substr($id,8);
            }
            $id = "http://irail.be/stations/NMBS/" . $id;
        }
        
        $stationsdocument = json_decode(file_get_contents(__DIR__ . self::$stationsfilename));
        
        foreach ($stationsdocument->{"@graph"} as $station) {
            if ($station->{"@id"} === $id) {
                return $station;
            }
        }
        return null;
    }
};
