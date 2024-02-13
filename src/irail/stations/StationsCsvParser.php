<?php

namespace irail\stations;

/**
 * A parser to read data directly from the stations.csv file.
 */
class StationsCsvParser
{

    const HEADER_URI = 'URI';
    const HEADER_NAME = 'name';
    const HEADER_ALT_FR = 'alternative-fr';
    const HEADER_ALT_NL = 'alternative-nl';
    const HEADER_ALT_DE = 'alternative-de';
    const HEADER_ALT_EN = 'alternative-en';
    const HEADER_TAF_TAP = 'taf-tap-code';
    const HEADER_TELEGRAPH = 'telegraph-code';
    const HEADER_COUNTRY_CODE = 'country-code';
    const HEADER_LONGITUDE = 'longitude';
    const HEADER_LATITUDE = 'latitude';
    const HEADER_AVG_STOP_TIMES = 'avg_stop_times';
    const HEADER_OFFICIAL_TRANSFER_TIME = 'official_transfer_time';

    /**
     * @param string $filename the absolute or relative path of the file to read
     * @return Station[] The stations indexed by their lowercase URI.
     */
    public static function parse(string $filename): array
    {
        $fileHandle = fopen($filename, 'r');

        $headers = fgetcsv($fileHandle);

        $uriIndex = array_search(self::HEADER_URI, $headers);
        $nameIndex = array_search(self::HEADER_NAME, $headers);
        $alternativeFrIndex = array_search(self::HEADER_ALT_FR, $headers);
        $alternativeNlIndex = array_search(self::HEADER_ALT_NL, $headers);
        $alternativeDeIndex = array_search(self::HEADER_ALT_DE, $headers);
        $alternativeEnIndex = array_search(self::HEADER_ALT_EN, $headers);
        $tapTafIndex = array_search(self::HEADER_TAF_TAP, $headers);
        $telegraphIndex = array_search(self::HEADER_TELEGRAPH, $headers);
        $countryCodeIndex = array_search(self::HEADER_COUNTRY_CODE, $headers);
        $longitudeIndex = array_search(self::HEADER_LONGITUDE, $headers);
        $latitudeIndex = array_search(self::HEADER_LATITUDE, $headers);
        $avgStopTimesIndex = array_search(self::HEADER_AVG_STOP_TIMES, $headers);
        $officialTransferTimeIndex = array_search(self::HEADER_OFFICIAL_TRANSFER_TIME, $headers);

        $results = [];
        while ($row = fgetcsv($fileHandle)) {
            $stationKey = strtolower($row[$uriIndex]);
            $results[$stationKey] = (new Station())
                ->setUri($row[$uriIndex])
                ->setName($row[$nameIndex])
                ->setAlternativeFr($row[$alternativeFrIndex])
                ->setAlternativeNl($row[$alternativeNlIndex])
                ->setAlternativeDe($row[$alternativeDeIndex])
                ->setAlternativeEn($row[$alternativeEnIndex])
                ->setTafTapCode($row[$tapTafIndex])
                ->setSymbolicName($row[$telegraphIndex])
                ->setCountryCode($row[$countryCodeIndex])
                ->setLongitude($row[$longitudeIndex])
                ->setLatitude($row[$latitudeIndex])
                ->setAvgStopTimes($row[$avgStopTimesIndex])
                ->setOfficialTransferTime($row[$officialTransferTimeIndex] ? intval($row[$officialTransferTimeIndex]) : null);
        }
        fclose($fileHandle);
        return $results;
    }
}