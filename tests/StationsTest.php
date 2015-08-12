<?php
//This class tests the PHP Stations class in src/irail/stations
use irail\stations\Stations;
use ML\JsonLD\JsonLD;

class StationsTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test whether returning all the stations works without problems
     */
    public function testAll ()
    {
        //Launch a query for everything
        $jsonld = Stations::getStations();
        //Assert whether it contains lots of stations
        $this->assertGreaterThan(600,$jsonld->{"@graph"});
    }

    /**
     * Test whether it's valid json-ld
     */
    public function testJsonLD ()
    {
        //Launch a query in various ways
        $jsonld1 = Stations::getStations("Brussel");
        $jsonld2 = Stations::getStations();

        //Assert whether the json ld is valid
        $doc1 = JsonLD::getDocument($jsonld1);
        $doc2 = JsonLD::getDocument($jsonld2);

        //Assert whether nodes exist
        //E.g., the default graph of doc1 should be http://irail.be/stations/NMBS?q=Brussel
        $this->assertTrue($doc1->containsGraph("http://irail.be/stations/NMBS?q=Brussel"));
        //E.g., the default graph of doc2 should be http://irail.be/stations/NMBS
        $this->assertTrue($doc2->containsGraph("http://irail.be/stations/NMBS"));
        
    }
    
    /**
     * Test Brussels in various ways: all queries should return the 6 stations with Brussels in their name
     */
    public function testBrussels ()
    {
        //Launch a query for Brussels in various ways
        $jsonld1 = Stations::getStations("Brussel");
        $jsonld2 = Stations::getStations("Brussels");
        $jsonld3 = Stations::getStations("Bruxelles");
        
        //Assert whether it contains the right number of stations
        $this->assertCount(6,$jsonld1->{"@graph"});
        $this->assertCount(6,$jsonld2->{"@graph"});
        $this->assertCount(6,$jsonld3->{"@graph"});
    }

    /**
     * Tests whether certain edge cases return the right identifier when looking at 
     *
     */
    public function testEdgeCases ()
    {
        //test whether something between brackets is ignored
        $result1 = Stations::getStations("Brussel Nat (be)");
        $this->assertCount(1,$result1->{"@graph"});

        //test whether sint st. and saint return the same result
        $result2a = Stations::getStations("st pancras");
        $result2b = Stations::getStations("saint pancras");
        
        $this->assertEquals($result2a->{"@graph"}[0]->{"@id"},$result2b->{"@graph"}[0]->{"@id"});
    }

    public function testId () 
    {
        //test whether the right object is returned
        $result1 = Stations::getStationFromID("008892007");
        $result2 = Stations::getStationFromID("BE.NMBS.008892007");
        $result3 = Stations::getStationFromID("http://irail.be/stations/NMBS/008892007");
        
        $this->assertEquals($result1->{"name"},$result2->{"name"});
        $this->assertEquals($result3->{"name"},$result2->{"name"});

    }
}
