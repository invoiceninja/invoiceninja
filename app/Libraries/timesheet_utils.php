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
    
    public static function curlGetUrls($urls = [], $timeout = 30) {
        // Create muxer
        $results = [];
        $multi = curl_multi_init();
        $handles = [];
        $ch2idx = [];
        try {
            foreach ($urls as $i => $url) {
                // Create new handle and add to muxer
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_ENCODING, "gzip");
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout in seconds

                curl_multi_add_handle($multi, $ch);
                $handles[(int) $ch] = $ch;
                $ch2idx[(int) $ch] = $i;
            }

            // Do initial connect
            $still_running = true;
            while ($still_running) {
                // Do curl stuff
                while (($mrc = curl_multi_exec($multi, $still_running)) === CURLM_CALL_MULTI_PERFORM);
                if ($mrc !== CURLM_OK) {
                    break;
                }

                // Try to read from handles that are ready
                while ($info = curl_multi_info_read($multi)) {
                    if ($info["result"] == CURLE_OK) {
                        $results[$ch2idx[(int) $info["handle"]]] = curl_multi_getcontent($info["handle"]);
                    } else {
                        if (CURLE_UNSUPPORTED_PROTOCOL == $info["result"]) {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Unsupported protocol"];
                        } else if (CURLE_URL_MALFORMAT == $info["result"]) {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Malform url"];
                        } else if (CURLE_COULDNT_RESOLVE_HOST == $info["result"]) {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Could not resolve host"];
                        } else if (CURLE_OPERATION_TIMEDOUT == $info["result"]) {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Timed out waiting for operations to finish"];
                        } else {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Unknown curl error code"];
                        }
                    }
                }

                // Sleep until
                if (($rs = curl_multi_select($multi)) === -1) {
                    usleep(20); // select failed for some reason, so we sleep for 20ms and run some more curl stuff
                }
            }
        } finally {
            foreach ($handles as $chi => $ch) {
                curl_multi_remove_handle($multi, $ch);
            }

            curl_multi_close($multi);
        }

        return $results;
    }
}
