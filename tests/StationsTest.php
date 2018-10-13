<?php

//This class tests the PHP Stations class in src/irail/stations
use irail\stations\Stations;

class StationsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test whether returning all the stations works without problems.
     */
    public function testAll()
    {
        //Launch a query for everything
        $jsonld = Stations::getStations();
        //Assert whether it contains lots of stations
        $this->assertGreaterThan(600, count($jsonld->{'@graph'}));
    }

    /**
     * Test whether it's valid json-ld.
     */
    /* public function testJsonLD()
     {
         //Launch a query in various ways
         $jsonld1 = Stations::getStations('Brussel');
         $jsonld2 = Stations::getStations();

         //Assert whether the json ld is valid
         $doc1 = JsonLD::getDocument($jsonld1);
         $doc2 = JsonLD::getDocument($jsonld2);

         //Assert whether nodes exist
         //E.g., the default graph of doc1 should be http://irail.be/stations/NMBS?q=Brussel
         $this->assertTrue($doc1->containsGraph('http://irail.be/stations/NMBS?q=Brussel'));
         //E.g., the default graph of doc2 should be http://irail.be/stations/NMBS
         $this->assertTrue($doc2->containsGraph('http://irail.be/stations/NMBS'));
     }

     /**
      * Test Brussels in various ways: all queries should return the 6 stations with Brussels in their name.
      */
    public function testBrussels()
    {
        //Launch a query for Brussels in various ways
        $jsonld1 = Stations::getStations('Brussel');
        $jsonld2 = Stations::getStations('Brussels');
        $jsonld3 = Stations::getStations('Bruxelles');
        $jsonld4 = Stations::getStations('Bru.-Noord / Brux.-Nord');
        $jsonld5 = Stations::getStations('Brussels Airport - Zaventem');
        //https://github.com/iRail/stations/issues/137
        $jsonld6 = Stations::getStations('Brux.-Midi');


        //Assert whether it contains the right number of stations
        $this->assertCount(6, $jsonld1->{'@graph'});
        $this->assertCount(6, $jsonld2->{'@graph'});
        $this->assertCount(6, $jsonld3->{'@graph'});
        $this->assertCount(1, $jsonld4->{'@graph'});
        $this->assertCount(1, $jsonld5->{'@graph'});
        $this->assertCount(1, $jsonld6->{'@graph'});
    }

    /**
     * Regression test for #128
     * When making a query, the station which matches best should be on top.
     */
    public function testExactNameQueries()
    {

        // If this passes, we're off for a good start. Verify all stations.
        $stations = Stations::getStations();
        foreach ($stations->{'@graph'} as $station) {
            echo("Testing exact match for $station->name...");
            $jsonld = Stations::getStations($station->name);
            $this->assertGreaterThanOrEqual(1, count($jsonld->{'@graph'}));
            $this->assertEquals($station->name, $jsonld->{'@graph'}[0]->name);
            $this->assertEquals($station->{'@id'}, $jsonld->{'@graph'}[0]->{'@id'});

            if (isset($station->alternative)) {
                // If this station in the list has an alternative form, try to match alternatives

                // If it's not an array, put it in an array. If it's an array, just copy.
                if (is_array($station->alternative)) {
                    $alternatives = $station->alternative;
                } else {
                    $alternatives = [$station->alternative];
                }

                foreach ($alternatives as $alternative) {
                    echo("Testing exact match for {$alternative->{"@value"}}...");
                    $jsonld = Stations::getStations($alternative->{"@value"});
                    $this->assertGreaterThanOrEqual(1, count($jsonld->{'@graph'}));
                    $this->assertEquals($station->name, $jsonld->{'@graph'}[0]->name);
                    $this->assertEquals($station->{'@id'}, $jsonld->{'@graph'}[0]->{'@id'});
                }
            }
        }

        // When searching for a station with an exact match, the exact match should be the first,
        // but other results should still be included! (Size must be greater than 1)
        $jsonld = Stations::getStations("hal");
        $this->assertGreaterThan(1, count($jsonld->{'@graph'}));

        $jsonld = Stations::getStations("halle");
        $this->assertGreaterThan(1, count($jsonld->{'@graph'}));

        $jsonld = Stations::getStations("asse");
        $this->assertGreaterThan(1, count($jsonld->{'@graph'}));

        $jsonld = Stations::getStations("heist");
        $this->assertGreaterThan(1, count($jsonld->{'@graph'}));

        $jsonld = Stations::getStations("mechelen");
        $this->assertGreaterThan(1, count($jsonld->{'@graph'}));

        $jsonld = Stations::getStations("mortsel");
        $this->assertGreaterThan(1, count($jsonld->{'@graph'}));
    }

    /**
     * Regression test for #129
     * Dashes and spaces should be handled correctly.
     */
    public function testSpacesAndDashes()
    {
        $names = ["Marne-la-Vallée - Chessy", "Marne-la-Vallée-Chessy", "Marne la Vallée Chessy", " Marne  - - la    Vallée  Chessy"];
        foreach ($names as $name) {
            $jsonld = Stations::getStations($name);
            $this->assertGreaterThanOrEqual(1, $jsonld->{'@graph'});
            $this->assertEquals("Marne-la-Vallée - Chessy", $jsonld->{'@graph'}[0]->name);
            $this->assertEquals("http://irail.be/stations/NMBS/008711184", $jsonld->{'@graph'}[0]->{'@id'});
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
        $this->assertEquals($result1a->{'@graph'}[0]->{'@id'}, $result1b->{'@graph'}[0]->{'@id'});
    }

    /**
     * Tests whether certain edge cases return the right identifier when looking at.
     */
    public function testEdgeCases()
    {
        //test whether something between brackets is ignored
        $result1 = Stations::getStations('Brussel Nat (be)');
        $this->assertCount(1, $result1->{'@graph'});

        //test whether sint st. and saint return the same result
        $result2a = Stations::getStations('st pancras');
        $result2b = Stations::getStations('saint pancras');
        $result2c = Stations::getStations('st-pancras');
        $result2d = Stations::getStations('st.-pancras');
        $this->assertEquals($result2a->{'@graph'}[0]->{'@id'}, $result2b->{'@graph'}[0]->{'@id'});
        $this->assertEquals($result2b->{'@graph'}[0]->{'@id'}, $result2c->{'@graph'}[0]->{'@id'});
        $this->assertEquals($result2a->{'@graph'}[0]->{'@id'}, $result2d->{'@graph'}[0]->{'@id'});

        // Check whether both am main and main work
        $result3a = Stations::getStations('frankfurt am main');
        $result3b = Stations::getStations('frankfurt main');
        $this->assertEquals($result3a->{'@graph'}[0]->{'@id'}, $result3b->{'@graph'}[0]->{'@id'});

        // Check whether flughhaven am main don't get mixed up and all work
        $result4a = Stations::getStations('frankfurt am main flughafen');
        $result4b = Stations::getStations('frankfurt flughafen');
        $result4c = Stations::getStations('frankfurt main flughafen');
        $this->assertEquals($result4a->{'@graph'}[0]->{'@id'}, $result4b->{'@graph'}[0]->{'@id'});
        $this->assertEquals($result4b->{'@graph'}[0]->{'@id'}, $result4c->{'@graph'}[0]->{'@id'});

        $result5a = Stations::getStations("braine l'alleud");
        $result5b = Stations::getStations('braine l alleud'); //for hafas purposes: https://github.com/iRail/hyperRail/issues/129
        $this->assertEquals($result5a->{'@graph'}[0]->{'@id'}, $result5b->{'@graph'}[0]->{'@id'});

        // Check whether a space after a - doesn't break the autocomplete: https://github.com/iRail/stations/issues/72
        $result6a = Stations::getStations('La Louviere- Centre');
        $result6b = Stations::getStations('La Louvière-Centre');
        $this->assertEquals($result6a->{'@graph'}[0]->{'@id'}, $result6b->{'@graph'}[0]->{'@id'});

        $result7a = Stations::getStations('Vivier D Oie');
        $result7b = Stations::getStations('Vivier D Oie / Diesdelle');
        $result7c = Stations::getStations('Diesdelle/Vivier d\'Oie');
        $this->assertEquals($result7a->{'@graph'}[0]->{'@id'}, $result7b->{'@graph'}[0]->{'@id'});
        $this->assertEquals($result7b->{'@graph'}[0]->{'@id'}, $result7c->{'@graph'}[0]->{'@id'});
    }

    public function testId()
    {
        //test whether the right object is returned
        $result1 = Stations::getStationFromID('008892007');
        $result2 = Stations::getStationFromID('BE.NMBS.008892007');
        $result3 = Stations::getStationFromID('http://irail.be/stations/NMBS/008892007');
        $noResult = Stations::getStationFromID('000000000');

        $this->assertEquals($result1->{'name'}, $result2->{'name'});
        $this->assertEquals($result3->{'name'}, $result2->{'name'});
        $this->assertNull($noResult);
    }

    public function testKapellen()
    {
        //test whether the right object is returned
        $result1 = Stations::getStationFromID('008821535');
        $result2 = Stations::getStations('Kapellen')->{'@graph'}[0];

        $result3 = Stations::getStationFromID('008200518');
        $result4 = Stations::getStations('Capellen')->{'@graph'}[0];

        $this->assertEquals($result1->{'@id'}, $result2->{'@id'});
        $this->assertEquals($result3->{'@id'}, $result4->{'@id'});
    }

    public function testSort()
    {
        //test whether Ghent Sint Pieters is the first object when searching for Belgian stations in a sorted fashion
        $results = Stations::getStations('Gent', 'be', true);
        $result1 = $results->{'@graph'}[0];
        $ghentsp = Stations::getStationFromID('http://irail.be/stations/NMBS/008892007');

        $this->assertEquals($ghentsp->{'name'}, $result1->{'name'});

        $results = Stations::getStations('Brussel', '', true);
        $result2 = $results->{'@graph'}[0];
        //The busiest station in Brussels is the south one
        $brusselssouth = Stations::getStations('Brussels South')->{'@graph'}[0];

        $this->assertEquals($result2->{'name'}, $brusselssouth->{'name'});
    }

    /**
     * Regression test for https://github.com/iRail/stations/issues/139
     */
    public function testAccentsInSearch()
    {
        $dusseldorfFlughafen = Stations::getStationFromID('http://irail.be/stations/NMBS/008039904');

        $results = Stations::getStations('Düsseldorf Flughafen Hbf', 'be', true);
        $this->assertGreaterThanOrEqual(1, count($results->{'@graph'}));
        $result1 = $results->{'@graph'}[0];

        $this->assertEquals($dusseldorfFlughafen->{'name'}, $result1->{'name'});
        $this->assertEquals($dusseldorfFlughafen->{'@id'}, $result1->{'@id'});

        $results = Stations::getStations('Dusseldorf Flughafen Hbf', 'be', true);
        $this->assertGreaterThanOrEqual(1, count($results->{'@graph'}));
        $result1 = $results->{'@graph'}[0];

        $this->assertEquals($dusseldorfFlughafen->{'name'}, $result1->{'name'});
        $this->assertEquals($dusseldorfFlughafen->{'@id'}, $result1->{'@id'});

    }

}
