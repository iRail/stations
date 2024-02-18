<?php

use irail\stations\Stations;
use PHPUnit\Framework\TestCase;

class StationsTest extends TestCase
{
    /**
     * Test whether returning all the stations works without problems.
     */
    public function testAll()
    {
        //Launch a query for everything
        $result = Stations::getStations();
        //Assert whether it contains lots of stations
        $this->assertGreaterThan(600, count($result));
    }


    /**
     * Test Brussels in various ways: all queries should return the 6 stations with Brussels in their name.
     */
    public function testBrussels()
    {
        //Launch a query for Brussels in various ways
        $result1 = Stations::getStations('Brussel');
        $result2 = Stations::getStations('Brussels');
        $result3 = Stations::getStations('Bruxelles');
        $result4 = Stations::getStations('Bru.-Noord / Brux.-Nord');
        $result5 = Stations::getStations('Brussels Airport - Zaventem');
        //https://github.com/iRail/stations/issues/137
        $result6 = Stations::getStations('Brux.-Midi');


        //Assert whether it contains the right number of stations
        $this->assertCount(6, $result1);
        $this->assertCount(6, $result2);
        $this->assertCount(6, $result3);
        $this->assertCount(1, $result4);
        $this->assertCount(1, $result5);
        $this->assertCount(1, $result6);
    }

    /**
     * Regression test for #128
     * When making a query, the station which matches best should be on top.
     */
    public function testExactNameQueries()
    {
        // If this passes, we're off for a good start. Verify all stations.
        $stations = Stations::getStations();
        echo("Testing exact name queries...");
        foreach ($stations as $station) {
            $result = Stations::getStations($station->getName());
            $this->assertGreaterThanOrEqual(1, count($result), $station->getName());
            $this->assertEquals($station->getName(), $result[0]->getName());
            $this->assertEquals($station->getUri(), $result[0]->getUri());

            foreach ($station->getLocalizedNames() as $alternative) {
                $result = Stations::getStations($alternative);
                $this->assertGreaterThanOrEqual(1, count($result));
                $this->assertEquals($station->getName(), $result[0]->getName());
                $this->assertEquals($station->getUri(), $result[0]->getUri());
            }
        }

        // When searching for a station with an exact match, the exact match should be the first,
        // but other results should still be included! (Size must be greater than 1)
        $result = Stations::getStations("hal");
        $this->assertGreaterThan(1, count($result));

        $result = Stations::getStations("halle");
        $this->assertGreaterThan(1, count($result));

        $result = Stations::getStations("asse");
        $this->assertGreaterThan(1, count($result));

        $result = Stations::getStations("heist");
        $this->assertGreaterThan(1, count($result));

        $result = Stations::getStations("mechelen");
        $this->assertGreaterThan(1, count($result));

        $result = Stations::getStations("mortsel");
        $this->assertGreaterThan(1, count($result));
    }

    /**
     * Regression test for #129
     * Dashes and spaces should be handled correctly.
     */
    public function testSpacesAndDashes()
    {
        $names = ["Marne-la-Vallée - Chessy", "Marne-la-Vallée-Chessy", "Marne la Vallée Chessy", " Marne  - - la    Vallée  Chessy"];
        foreach ($names as $name) {
            $station = Stations::getStations($name);
            $this->assertGreaterThanOrEqual(1, $station);
            $this->assertEquals("Marne-la-Vallée - Chessy", $station[0]->getName());
            $this->assertEquals("http://irail.be/stations/NMBS/008711184", $station[0]->getUri());
        }
    }

    /**
     * Test UTF-8 queries.
     */
    public function testEncoding()
    {
        //Launch a query for Brussels in various ways
        $result1a = Stations::getStations('Ville-Pommerœul');
        $result1b = Stations::getStations('Ville-Pommeroeul');
        $this->assertEquals($result1a[0]->getUri(), $result1b[0]->getUri());
    }

    /**
     * Tests whether certain edge cases return the right identifier when looking at.
     */
    public function testEdgeCases()
    {
        //test whether something between brackets is ignored
        $result1 = Stations::getStations('Brussel Nat (be)');
        $this->assertCount(1, $result1);

        //test whether sint st. and saint return the same result
        $result2a = Stations::getStations('st pancras');
        $result2b = Stations::getStations('saint pancras');
        $result2c = Stations::getStations('st-pancras');
        $result2d = Stations::getStations('st.-pancras');
        $this->assertEquals($result2a[0]->getUri(), $result2b[0]->getUri());
        $this->assertEquals($result2b[0]->getUri(), $result2c[0]->getUri());
        $this->assertEquals($result2a[0]->getUri(), $result2d[0]->getUri());

        // Check whether both am main and main work
        $result3a = Stations::getStations('frankfurt am main');
        $result3b = Stations::getStations('frankfurt main');
        $this->assertEquals($result3a[0]->getUri(), $result3b[0]->getUri());

        // Check whether flughhaven am main don't get mixed up and all work
        $result4a = Stations::getStations('frankfurt am main flughafen');
        $result4b = Stations::getStations('frankfurt flughafen');
        $result4c = Stations::getStations('frankfurt main flughafen');
        $this->assertEquals($result4a[0]->getUri(), $result4b[0]->getUri());
        $this->assertEquals($result4b[0]->getUri(), $result4c[0]->getUri());

        $result5a = Stations::getStations("braine l'alleud");
        $result5b = Stations::getStations('braine l alleud'); //for hafas purposes: https://github.com/iRail/hyperRail/issues/129
        $this->assertEquals($result5a[0]->getUri(), $result5b[0]->getUri());

        // Check whether a space after a - doesn't break the autocomplete: https://github.com/iRail/stations/issues/72
        $result6a = Stations::getStations('La Louviere- Centre');
        $result6b = Stations::getStations('La Louvière-Centre');
        $this->assertEquals($result6a[0]->getUri(), $result6b[0]->getUri());

        $result7a = Stations::getStations('Vivier D Oie');
        $result7b = Stations::getStations('Vivier D Oie / Diesdelle');
        $result7c = Stations::getStations('Diesdelle/Vivier d\'Oie');
        $this->assertEquals($result7a[0]->getUri(), $result7b[0]->getUri());
        $this->assertEquals($result7b[0]->getUri(), $result7c[0]->getUri());
    }

    public function testId()
    {
        //test whether the right object is returned
        $result1 = Stations::getStationFromID('008892007');
        $result2 = Stations::getStationFromID('BE.NMBS.008892007');
        $result3 = Stations::getStationFromID('http://irail.be/stations/NMBS/008892007');
        $noResult = Stations::getStationFromID('000000000');

        $this->assertEquals($result1->getName(), $result2->getName());
        $this->assertEquals($result3->getName(), $result2->getName());
        $this->assertNull($noResult);
    }

    public function testKapellen()
    {
        //test whether the right object is returned
        $result1 = Stations::getStationFromID('008821535');
        $result2 = Stations::getStations('Kapellen')[0];

        $result3 = Stations::getStationFromID('008200518');
        $result4 = Stations::getStations('Capellen')[0];

        $this->assertEquals($result1->getUri(), $result2->getUri());
        $this->assertEquals($result3->getUri(), $result4->getUri());
    }

    public function testSort()
    {
        //test whether Ghent Sint Pieters is the first object when searching for Belgian stations in a sorted fashion
        $results = Stations::getStations('Gent', 'be', true);
        $result1 = $results[0];
        $ghentsp = Stations::getStationFromID('http://irail.be/stations/NMBS/008892007');

        $this->assertEquals($ghentsp->getName(), $result1->getName());

        $results = Stations::getStations('Brussel', '', true);
        $result2 = $results[0];
        //The busiest station in Brussels is the south one
        $brusselssouth = Stations::getStations('Brussels South')[0];

        $this->assertEquals($result2->getName(), $brusselssouth->getName());
    }

    /**
     * Regression test for https://github.com/iRail/stations/issues/139
     */
    public function testAccentsInSearch()
    {
        $dusseldorfFlughafen = Stations::getStationFromID('http://irail.be/stations/NMBS/008039904');

        $results = Stations::getStations('Düsseldorf Flughafen Hbf', 'de', true);
        $this->assertGreaterThanOrEqual(1, count($results));
        $result1 = $results[0];

        $this->assertEquals($dusseldorfFlughafen->getName(), $result1->getName());
        $this->assertEquals($dusseldorfFlughafen->getUri(), $result1->getUri());

        $results = Stations::getStations('Dusseldorf Flughafen Hbf', 'de', true);
        $this->assertGreaterThanOrEqual(1, count($results));
        $result1 = $results[0];

        $this->assertEquals($dusseldorfFlughafen->getName(), $result1->getName());
        $this->assertEquals($dusseldorfFlughafen->getUri(), $result1->getUri());

    }

}
