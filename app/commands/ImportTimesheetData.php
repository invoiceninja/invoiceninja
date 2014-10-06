<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ImportTimesheetData extends Command {

    protected $name = 'ninja:import-timesheet-data';
    protected $description = 'Import timesheet data';
    
    public function fire() {
        $this->info(date('Y-m-d') . ' Running ImportTimesheetData...');
        
        // Seems we are using the console timezone
        DB::statement("SET SESSION time_zone = '+00:00'");
        
        // Get the Unix epoch
        $unix_epoch = new DateTime('1970-01-01T00:00:01', new DateTimeZone("UTC"));
        
        // Create some initial sources we can test with
        $user = User::first();
        if (!$user) {
            $this->error("Error: please create user account by logging in");
            return;
        }
        
        // TODO: Populate with own test data until test data has been created
        // Truncate the tables
        /*$this->info("Truncate tables");
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('projects')->truncate();
        DB::table('project_codes')->truncate();
        DB::table('timesheet_event_sources')->truncate();
        DB::table('timesheet_events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;'); */
        
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
                //$event_source->from_date = new DateTime("2009-01-01");
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
    
        //FIXME: Make sure we keep track of duplicate UID's so we don't fail when inserting them to the database
        $this->info("Start parsing ICAL files");
        foreach ($event_sources as $i => $event_source) {
            if (!is_array($results[$i])) {
                $this->info("Find events in " . $event_source->name);
                file_put_contents("/tmp/" . $event_source->name . ".ical", $results[$i]);
                if (preg_match_all('/BEGIN:VEVENT\r?\n(.+?)\r?\nEND:VEVENT/s', $results[$i], $icalmatches)) {
                    $this->info("Found ".(count($icalmatches[1])-1)." events\n");
                    // Fetch all uids so we can do a quick lookup
                    $uids = [];
                    $event_source->events()->get(['uid', 'org_updated_at'])->map(function($item) use(&$uids) {                        
                        $uids[$item->uid] = $item->org_updated_at;
                    });

                    // Loop over all the found events
                    foreach ($icalmatches[1] as $eventstr) {
                        //print "---\n";
                        //print $eventstr."\n";
                        //print "---\n";
                        //$this->info("Match event");
                        # Fix lines broken by 76 char limit
                        $eventstr = preg_replace('/\r?\n\s/s', '', $eventstr);
                        //$this->info("Parse data");
                        $data = TimesheetUtils::parseICALEvent($eventstr);
                        if ($data) {
                            // Extract code for summary so we only import events we use
                            list($codename, $tags, $title) = TimesheetUtils::parseEventSummary($data['summary']);
                            if ($codename != null) {
                                $event = TimesheetEvent::createNew($user);
                                $event->uid = $data['uid'];
                                
                                # Add RECURRENCE-ID to the UID to make sure the event is unique
                                if (isset($data['recurrence-id'])) {
                                    $event->uid .= "::".$data['recurrence-id'];
                                }
                                
                                //FIXME: Bail on RRULE as we don't support that

                                // Convert to DateTime objects
                                foreach (['dtstart', 'dtend', 'created', 'last-modified'] as $key) {
                                    // Parse and create DataTime object from ICAL format
                                    list($dt, $timezone) = TimesheetUtils::parseICALDate($data[$key]);

                                    // Handle bad dates in created and last-modified
                                    if ($dt == null || $dt < $unix_epoch) {
                                        if ($key == 'created' || $key == 'last-modified') {
                                            $dt = $unix_epoch; // Default to UNIX epoch
                                            $event->import_error = "Could not parse date for $key: '" . $data[$key] . "' so default to UNIX Epoc\n";
                                        } else {
                                            echo "Could not parse date for $key: '" . $data[$key] . "'\n";
                                            exit(255); // TODO: Bail on this event or write to error table
                                        }
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
                                        case 'created': 
                                            $event->org_created_at = $dt;
                                            break;
                                        case 'last-modified': 
                                            $event->org_updated_at = $dt;
                                            break;
                                    }
                                }

                                // Check that we are witin the range
                                if ($event_source->from_date != null) {
                                    $from_date = new DateTime($event_source->from_date, new DateTimeZone('UTC'));
                                    if ($from_date > $event->end_date) {
                                        // Skip this event
                                        echo "Skiped: $codename: $title\n";
                                        continue;
                                    }
                                }
                                
                                // Check for events we allready have
                                if (isset($uids[$event->uid])) {
                                    $db_event_org_updated_at = new DateTime($uids[$event->uid], new DateTimeZone('UTC'));
                                    if($event->org_updated_at <= $db_event_org_updated_at) {
                                        // Same or older version of new event so skip
                                        continue;
                                        
                                    } else {
                                        var_dump($event->uid);
                                        var_dump($uids[$event->uid]);
                                        var_dump($event);
                                        // updated version of the event
                                        echo "update version of the event\n";
                                        
                                    }
                                }
                                $uids[$event->uid] = $event->org_updated_at;
                                
                                // Calculate number of hours 
                                $di = $event->end_date->diff($event->start_date);
                                $event->hours = $di->h + $di->i / 60;

                                // Copy data to new object
                                $event->org_data = $eventstr;
                                $event->summary = $title;
                                $event->description = $title;
                                $event->org_code = $codename;
                                $event->owner = $event_source->owner;
                                $event->timesheet_event_source_id = $event_source->id;
                                if (isset($codes[$codename])) {
                                    $event->project_id = $codes[$codename]->project_id;
                                    $event->project_code_id = $codes[$codename]->id;
                                }
                                if (isset($data['location'])) {
                                    $event->location = $data['location'];
                                }

                                try {
                                    $this->info("Save event: ".$event->summary);
                                    //if($event->uid == '2nvv4qjnlc293vq3h2833uslhc@google.com') {
                                        //var_dump($event);
                                        $event->save();
                                    //}
                                } catch (Exception $ex) {
                                    echo "'" . $event->summary . "'\n";
                                    var_dump($data);
                                    echo $ex->getMessage();
                                    echo $ex->getTraceAsString();
                                    exit(0);
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

    protected function getArguments() {
        return array(
        );
    }

    protected function getOptions() {
        return array(
        );
    }

}
