<?php

class TimesheetUtilTest extends \PHPUnit_Framework_TestCase {

    public function testParseEventSummary() {
        list($code, $codes, $title) = TimesheetUtils::parseEventSummary('Riga :)');
        $this->assertSame(null, $code);
        
        list($code, $tags, $title) = TimesheetUtils::parseEventSummary('Test:');
        $this->assertSame("TEST", $code);
        
        list($code, $tags, $title) = TimesheetUtils::parseEventSummary('Test: ');
        $this->assertSame("TEST", $code);
         
        list($code, $tags, $title) = TimesheetUtils::parseEventSummary('Test::');
        $this->assertSame("TEST", $code);
        
        list($code, $tags, $title) = TimesheetUtils::parseEventSummary('TEST: Hello :)');
        $this->assertSame("TEST", $code);
        
        list($code, $tags, $title) = TimesheetUtils::parseEventSummary('Test/tags: ');
        $this->assertSame('TEST', $code);
        $this->assertSame('tags', $tags);
    }

}
