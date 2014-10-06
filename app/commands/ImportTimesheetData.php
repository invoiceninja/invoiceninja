<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ImportTimesheetData extends Command {

	protected $name = 'ninja:import-timesheet-data';
	protected $description = 'Import timesheet data';

    public function fire()
	{
		$this->info(date('Y-m-d') . ' Running ImportTimesheetData...');

       
        /* try {
            $dt = new DateTime("now");
            var_dump($dt);
            echo "1:".$dt."\n";
            echo $dt->getTimestamp()."\n";
        } catch (Exception $ex) {
            echo $ex->getMessage();
            echo $ex->getTraceAsString();
        }
        exit(0); */
   
        
        
        
        
        
        // Create some initial sources we can test with
        $user = User::first();
        if (!$user) {
            $this->error("Error: please create user account by logging in");
            return;
        }
        
        // TODO: Populate with own test data until test data has been created
                
        // Truncate the tables
        $this->info("Truncate tables");
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('projects')->truncate();
        DB::table('project_codes')->truncate();
        DB::table('timesheet_event_sources')->truncate();
        DB::table('timesheet_events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        if (!Project::find(1)) {
            $this->info("Import old project codes");
            $oldcodes = json_decode(file_get_contents("/home/tlb/git/itktime/codes.json"), true);
            foreach ($oldcodes as $name => $options) {
                $project = Project::createNew($user);
                $project->name = $options['description'];
                $project->save();
                
                $code = ProjectCode::createNew($user);
                $code->name = $name;
                $project->codes()->save($code);
            }
            #Project::createNew($user);
        }
                
        if (!TimesheetEventSource::find(1)) {
            $this->info("Import old event sources");
            
            $oldevent_sources = json_decode(file_get_contents("/home/tlb/git/itktime/employes.json"), true);

            //array_shift($oldevent_sources);
            //array_pop($oldevent_sources);
            
            foreach ($oldevent_sources as $source) {
                $event_source = TimesheetEventSource::createNew($user);
                $event_source->name = $source['name'];
                $event_source->url = $source['url'];
                $event_source->owner = $source['owner'];
                $event_source->type = 'ical';
                $event_source->save();
            }
        }
        
        // Add all URL's to Curl
        $this->info("Download ICAL feeds");
        $event_sources = TimesheetEventSource::all(); // TODO: Filter based on ical feeds
        
        $urls = [];
        $event_sources->map(function($item) use(&$urls) {
            $urls[] = $item->url;
        });
        $results = $this->curlGetUrls($urls);

        // Fetch all codes so we can do a quick lookup
        $codes = array();        
        ProjectCode::all()->map(function($item) use(&$codes) {
            $codes[$item->name] = $item;
        });
                
        $this->info("Start parsing ICAL files");
        foreach($event_sources as $i => $event_source) {
            if(!is_array($results[$i])) {
                $this->info("Find events in ".$event_source->name);
                if(preg_match_all('/BEGIN:VEVENT\r?\n(.+?)\r?\nEND:VEVENT/s', $results[$i], $icalmatches)) {
                    foreach($icalmatches[1] as $eventstr) {
                        //print "---\n";
                        //print $eventstr."\n";
                        //print "---\n";
                        //$this->info("Match event");
                        # Fix lines broken by 76 char limit
                        $eventstr = preg_replace('/\r?\n\s/s', '', $eventstr);
                        //$this->info("Parse data");
                        if(preg_match_all('/(?:^|\r?\n)([^;:]+)[;:]([^\r\n]+)/s', $eventstr, $eventmatches)) {
                            // Build ICAL event array
                            $data = ['summary' => ''];
                            foreach($eventmatches[1] as $i => $key) {
                                # Convert escaped linebreakes to linebreak
                                $value = preg_replace("/\r?\n\s/", "", $eventmatches[2][$i]);
                                # Unescape , and ;
                                $value = preg_replace('/\\\\([,;])/s', '$1', $value);
                                $data[strtolower($key)] = $value;
                            }
                            
                            // Extract code for summary so we only import events we use
                            //$this->info("Match summary");
                            if(preg_match('/^\s*([^\s:\/]+)(?:\/([^:]+))?\s*:\s*(.*?)\s*$/s', $data['summary'], $matches)) {
                                $codename = strtoupper($matches[1]);
                                $tags = strtolower($matches[2]);
                                $title = $matches[3];
                            
                                //$this->info("Check code");
                                if(isset($codes[$codename])) {
                                    //var_dump($data);
                                    $code = $codes[$codename];
                                    $event = TimesheetEvent::createNew($user);
                                    $event->summary = $title;
                                    $event->description = $title;
                                    $event->owner = $event_source->owner;
                                    $event->timesheet_event_source_id = $event_source->id;
                                    $event->project_id = $code->project_id;
                                    $event->project_code_id = $code->id;                                    
                                    $event->uid = $data['uid'];
                                    //$event->org_data = $eventstr;
                                    
                                    if(isset($data['location'])) {
                                        $event->location = $data['location'];
                                    }

                                    foreach (['dtstart', 'dtend', 'created', 'last-modified'] as $key) {
                                        // Parse and create DataTime object from ICAL format
                                        $dt = null;
                                        $timezone = null;
                                        if (preg_match('/^TZID=(.+?):([12]\d\d\d)(\d\d)(\d\d)T(\d\d)(\d\d)(\d\d)$/', $data[$key], $m)) {
                                            $timezone = $m[1];
                                            $dt = new DateTime("{$m[2]}-{$m[3]}-{$m[4]}T{$m[5]}:{$m[6]}:{$m[7]}", new DateTimeZone($m[1]));
                                        } else if (preg_match('/^VALUE=DATE:([12]\d\d\d)(\d\d)(\d\d)$/', $data[$key], $m)) {
                                            $dt = new DateTime("{$m[1]}-{$m[2]}-{$m[3]}T00:00:00", new DateTimeZone("UTC"));
                                        } else if (preg_match('/^([12]\d\d\d)(\d\d)(\d\d)T(\d\d)(\d\d)(\d\d)Z$/', $data[$key], $m)) {
                                            $dt = new DateTime("{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}", new DateTimeZone("UTC"));
                                        } else if($key == 'created' || $key == 'last-modified') {
                                            $dt = new DateTime('1970-01-01T00:00:00', new DateTimeZone("UTC")); // Default to UNIX epoc
                                            echo "Could not parse date for $key: '".$data[$key]."' so default to UNIX Epoc\n"; // TODO write to error table
                                        } else {
                                            echo "Could not parse date for $key: '".$data[$key]."'\n"; // TODO write to error table
                                            exit(255); // TODO: Bail onthis event
                                        }
                                                                   
                                        // Assign DateTime object to
                                        switch ($key) {
                                            case 'dtstart': 
                                                $event->start_date = $dt;
                                                $event->org_start_date_timezone = $timezone;
                                                break;
                                            case 'dtend':
                                                $event->end_date = $dt;
                                                $event->org_end_date_timezone = $timezone;
                                                break;
                                            case 'created': $event->org_created_at = $dt; break;
                                            case 'last-modified': $event->org_updated_at = $dt; break;
                                        }                                        
                                    }
                                    
                                    // Calculate number of hours 
                                    $di = $event->end_date->diff($event->start_date);
                                    $event->hours = $di->h + $di->i / 60;
                                    
                                    /*var_dump($event);
                                    exit();*/
                                    
                                    // Save event
                                    echo "'".$event->summary."'\n";
                                    if(preg_match("/forbered møde med Peter Pietras - nyt sjovt projekt./", $event->summary)) {
                                        try {
                                            //$event->start_date = new DateTime("");
                                            $event->save();
                                        } catch (Exception $ex) {
                                            var_dump($data);
                                            echo $ex->getMessage();
                                            echo $ex->getTraceAsString();
                                            exit();
                                        }
                                    }
                                    
                                } else {
                                    //TODO: Add to error table so we can show user
                                    echo "Code not found: $codename\n";
                                }
                            }
                            
                        }
                    }
                    
                } else {
                    // Parse error
                }
                
            } else {
                // Curl Error
            }
        }
        
        $this->info('Done');
	}

    private function curlGetUrls($urls = [], $timeout = 30) {
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
                curl_setopt($ch, CURLOPT_ENCODING , "gzip");
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); 
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout in seconds
                
                curl_multi_add_handle($multi, $ch);
                $handles[(int) $ch] = $ch;
                $ch2idx[(int) $ch] = $i;
            }

            // Do initial connect
            $still_running = true;
            while($still_running) {
                // Do curl stuff
                while (($mrc = curl_multi_exec($multi, $still_running)) === CURLM_CALL_MULTI_PERFORM);
                if ($mrc !== CURLM_OK) { break; }
                
                // Try to read from handles that are ready
                while ($info = curl_multi_info_read($multi)) {
                    if ($info["result"] == CURLE_OK) {
                        $results[$ch2idx[(int) $info["handle"]]] = curl_multi_getcontent($info["handle"]);
                        
                    } else {
                        if(CURLE_UNSUPPORTED_PROTOCOL == $info["result"]) {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Unsupported protocol"];
                        
                        } else if(CURLE_URL_MALFORMAT == $info["result"]) {
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Malform url"];
                            
                        } else if(CURLE_COULDNT_RESOLVE_HOST == $info["result"]){
                            $results[$ch2idx[(int) $info["handle"]]] = [$info["result"], "Could not resolve host"];
                        
                        } else if(CURLE_OPERATION_TIMEDOUT == $info["result"]){
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
    
    protected function getArguments()
	{
		return array(
			
		);
	}

	protected function getOptions()
	{
		return array(
			
		);
	}

}