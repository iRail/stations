<?php

namespace irail\stations;

/**
 * A station in the Irail/Stations dataset
 */
class Station
{
    private string $uri;
    private string $name;
    private ?string $alternative_fr;
    private ?string $alternative_nl;
    private ?string $alternative_de;
    private ?string $alternative_en;
    private ?string $taf_tap_code;
    private ?string $symbolicName;
    private ?string $country_code;
    private float $longitude;
    private float $latitude;
    private ?float $avg_stop_times;
    private ?int $official_transfer_time;

    /**
     * A unique identifier in the form of a URI.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @param string $uri
     * @return Station
     */
    public function setUri(string $uri): Station
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * The most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name).
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Station
     */
    public function setName(string $name): Station
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Alternative name in French, if available
     * @return string|null
     */
    public function getAlternativeFr(): ?string
    {
        return $this->alternative_fr;
    }

    /**
     * @param string|null $alternative_fr
     * @return Station
     */
    public function setAlternativeFr(?string $alternative_fr): Station
    {
        $this->alternative_fr = $alternative_fr;
        return $this;
    }

    /**
     * Alternative name in Dutch, if available
     * @return string|null
     */
    public function getAlternativeNl(): ?string
    {
        return $this->alternative_nl;
    }

    /**
     * @param string|null $alternative_nl
     * @return Station
     */
    public function setAlternativeNl(?string $alternative_nl): Station
    {
        $this->alternative_nl = $alternative_nl;
        return $this;
    }

    /**
     * Alternative name in German, if available
     * @return string|null
     */
    public function getAlternativeDe(): ?string
    {
        return $this->alternative_de;
    }

    /**
     * @param string|null $alternative_de
     * @return Station
     */
    public function setAlternativeDe(?string $alternative_de): Station
    {
        $this->alternative_de = $alternative_de;
        return $this;
    }

    /**
     * Alternative name in English, if available
     * @return string|null
     */
    public function getAlternativeEn(): ?string
    {
        return $this->alternative_en;
    }

    /**
     * @param string|null $alternative_en
     * @return Station
     */
    public function setAlternativeEn(?string $alternative_en): Station
    {
        $this->alternative_en = $alternative_en;
        return $this;
    }

    /**
     * TSI TAF/TAP (Technical Specification for Interoperability, Telematics Applications for Freight/Passengers). Used by Infrabel and in some NMBS/SNCB data.
     *
     * @return string|null
     */
    public function getTafTapCode(): ?string
    {
        return $this->taf_tap_code;
    }

    /**
     * @param string|null $taf_tap_code
     * @return Station
     */
    public function setTafTapCode(?string $taf_tap_code): Station
    {
        $this->taf_tap_code = $taf_tap_code;
        return $this;
    }

    /**
     * Symbolic name for Belgian stations, used by NMBS/SNCB internally. Also known as telegraph code.
     * @return string|null
     */
    public function getSymbolicName(): ?string
    {
        return $this->symbolicName;
    }

    /**
     * Symbolic name for Belgian stations, used by NMBS/SNCB internally. Also known as telegraph code.
     * @param string|null $symbolicName
     * @return Station
     */
    public function setSymbolicName(?string $symbolicName): Station
    {
        $this->symbolicName = $symbolicName;
        return $this;
    }

    /**
     * The ISO2 country code for the station.
     * @return string|null
     */
    public function getCountryCode(): ?string
    {
        return $this->country_code;
    }

    /**
     * @param string|null $country_code
     * @return Station
     */
    public function setCountryCode(?string $country_code): Station
    {
        $this->country_code = $country_code;
        return $this;
    }

    /**
     * The longitude of the station.
     * @return float
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * @param float $longitude
     * @return Station
     */
    public function setLongitude(float $longitude): Station
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * The latitude of the station.
     * @return float
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * @param float $latitude
     * @return Station
     */
    public function setLatitude(float $latitude): Station
    {
        $this->latitude = $latitude;
        return $this;
    }

    /**
     * The average number of vehicles stopping each day in this station. Relative number which indicates how much traffic a station gets.
     * @return float|null
     */
    public function getAvgStopTimes(): ?float
    {
        return $this->avg_stop_times;
    }

    /**
     * @param float|null $avg_stop_times
     * @return Station
     */
    public function setAvgStopTimes(?float $avg_stop_times): Station
    {
        $this->avg_stop_times = $avg_stop_times;
        return $this;
    }

    /**
     * The time needed for an average person to make a transfer in this station, according to official sources (NMBS/SNCB).
     * @return int|null
     */
    public function getOfficialTransferTime(): ?int
    {
        return $this->official_transfer_time;
    }

    /**
     * @param int|null $official_transfer_time
     * @return Station
     */
    public function setOfficialTransferTime(?int $official_transfer_time): Station
    {
        $this->official_transfer_time = $official_transfer_time;
        return $this;
    }

    /**
     * Get the localized station name for each language if it is available, with a fallback to the neutral name if no local name is available for the given language.
     * @return array{'nl': string, 'fr': string, 'de': string, 'en': string}
     */
    public function getLocalizedNames(): array
    {
        return [
            'nl' => $this->getAlternativeNl() ?: $this->getName(),
            'fr' => $this->getAlternativeFr() ?: $this->getName(),
            'de' => $this->getAlternativeDe() ?: $this->getName(),
            'en' => $this->getAlternativeEn() ?: $this->getName(),
        ];
    }

}