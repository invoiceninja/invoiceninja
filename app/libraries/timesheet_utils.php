<?php

class TimesheetUtils
{
    public static function parseEventSummary($summary) {
        if (preg_match('/^\s*([^\s:\/]+)(?:\/([^:]+))?\s*:\s*([^)].*$|$)$/s', $summary, $matches)) {
            return [strtoupper($matches[1]), strtolower($matches[2]), $matches[3]];
        } else {
            return false;
        }
    }

    public static function parseICALEvent($eventstr) {
        if (preg_match_all('/(?:^|\r?\n)([^;:]+)[;:]([^\r\n]+)/s', $eventstr, $matches)) {
            // Build ICAL event array
            $data = ['summary' => ''];
            foreach ($matches[1] as $i => $key) {
                # Convert escaped linebreakes to linebreak
                $value = preg_replace("/\r?\n\s/", "", $matches[2][$i]);
                # Unescape , and ;
                $value = preg_replace('/\\\\([,;])/s', '$1', $value);
                $data[strtolower($key)] = $value;
            }
            return $data;
        } else {
            return false;
        }
    }
    
    
    public static function parseICALDate($datestr) {
        $dt = null;
        $timezone = null;
        if (preg_match('/^TZID=(.+?):([12]\d\d\d)(\d\d)(\d\d)T(\d\d)(\d\d)(\d\d)$/', $datestr, $m)) {
            $timezone = $m[1];
            $dt = new DateTime("{$m[2]}-{$m[3]}-{$m[4]}T{$m[5]}:{$m[6]}:{$m[7]}", new DateTimeZone($m[1]));
            
        } else if (preg_match('/^VALUE=DATE:([12]\d\d\d)(\d\d)(\d\d)$/', $datestr, $m)) {
            $dt = new DateTime("{$m[1]}-{$m[2]}-{$m[3]}T00:00:00", new DateTimeZone("UTC"));
            
        } else if (preg_match('/^([12]\d\d\d)(\d\d)(\d\d)T(\d\d)(\d\d)(\d\d)Z$/', $datestr, $m)) {
            $dt = new DateTime("{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}", new DateTimeZone("UTC"));
            
        } else {
            return false;
        }
        
        // Convert all to UTC
        if($dt->getTimezone()->getName() != 'UTC') {
            $dt->setTimezone(new DateTimeZone('UTC'));
        }
        
        return [$dt, $timezone];
    }
}
