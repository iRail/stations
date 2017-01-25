<?php

//This class tests the PHP Stations class in src/irail/stations
use irail\stations\Stations;
use ML\JsonLD\JsonLD;

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
        $this->assertGreaterThan(600, $jsonld->{'@graph'});
    }

    /**
     * Test whether it's valid json-ld.
     */
    public function testJsonLD()
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


        //Assert whether it contains the right number of stations
        $this->assertCount(6, $jsonld1->{'@graph'});
        $this->assertCount(6, $jsonld2->{'@graph'});
        $this->assertCount(6, $jsonld3->{'@graph'});
        $this->assertCount(1, $jsonld4->{'@graph'});
        $this->assertCount(1, $jsonld5->{'@graph'});
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

        $this->assertEquals($result1->{'name'}, $result2->{'name'});
        $this->assertEquals($result3->{'name'}, $result2->{'name'});
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

        $this->assertEquals($result1->{'name'}, $ghentsp->{'name'});

        $results = Stations::getStations('Brussel', '', true);
        $result2 = $results->{'@graph'}[0];
        //The busiest station in Brussels is the south one
        $brusselssouth = Stations::getStations('Brussels South')->{'@graph'}[0];

        $this->assertEquals($result2->{'name'}, $brusselssouth->{'name'});
    }


    function testPerformance()
    {
        apc_clear_cache();
        // test sample should be large enough.
        // 100 iterations of 50 stations for a good average.
        $n = 10;

        $time_pre = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            // 51 stations, Brussels south at 8:00
            // stations are in order of appearance.
            // This should somewhat represent a real series of requests.
            Stations::getStations("Brussels-South/Brussels-Midi");
            Stations::getStations("Nivelles");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Antwerpen-Centraal");
            Stations::getStations("Braine-Le-Comte");
            Stations::getStations("Liege-Palais");
            Stations::getStations("Oostende");
            Stations::getStations("Brussels Airport - Zaventem");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Dendermonde");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Genk");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Tournai");
            Stations::getStations("Charleroi-Sud");
            Stations::getStations("Landen");
            Stations::getStations("Louvain-La-Neuve-Univ.");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Sint-Niklaas");
            Stations::getStations("Gent-Sint-Pieters");
            Stations::getStations("Antwerpen-Centraal");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Brugge");
            Stations::getStations("Nivelles");
            Stations::getStations("Quievrain");
            Stations::getStations("Brussels Airport - Zaventem");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Oudenaarde");
            Stations::getStations("Leuven");
            Stations::getStations("Essen");
            Stations::getStations("Binche");
            Stations::getStations("Leuven");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Luxembourg (l)");
            Stations::getStations("Turnhout");
            Stations::getStations("Braine-Le-Comte");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Kortrijk");
            Stations::getStations("Dendermonde");
            Stations::getStations("Antwerpen-Centraal");
            Stations::getStations("Liege-Guillemins");
            Stations::getStations("Oostende");
            Stations::getStations("Brussels Airport - Zaventem");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Charleroi-Sud");
            Stations::getStations("Tongeren");
            Stations::getStations("Schaarbeek / Schaerbeek");
            Stations::getStations("Amsterdam Cs (nl)");
            Stations::getStations("Kortrijk");
        }

        $time_post = microtime(true);
        $exec_time = $time_post - $time_pre;
        $exec_time = ($exec_time * 1000) / $n;

        $status = (extension_loaded('apc') && ini_get('apc.enabled') && ini_get('apc.enable_cli')) ? "enabled" : "disabled";

        echo "Testing $n liveboards took an average of $exec_time ms for 1 liveboard, with APC $status.\n";

        if ($status == "disabled") {
            echo "Use '-d apc.enable_cli=1' in the phpunit argument to test with APC enabled.\n";
        }

        assert(extension_loaded('apc') && ini_get('apc.enabled'), "APC should be enabled!");
    }

}
