<?php

//TODO: Add support for recurring event : https://github.com/tplaner/When

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use PHPBenchTime\Timer;

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
        }

        if (!TimesheetEventSource::find(1)) {
            $this->info("Import old event sources");

            $oldevent_sources = json_decode(file_get_contents("/home/tlb/git/itktime/employes.json"), true);

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
        $T = new Timer;
        $T->start();

        $T->lap("Get Event Sources");
        $event_sources = TimesheetEventSource::all(); // TODO: Filter based on ical feeds
        
        $T->lap("Get ICAL responses");
        $urls = [];
        $event_sources->map(function($item) use(&$urls) {
            $urls[] = $item->url;
        });
        $icalresponses = TimesheetUtils::curlGetUrls($urls);
        
        $T->lap("Fetch all codes so we can do a quick lookup");
        $codes = array();
        ProjectCode::all()->map(function($item) use(&$codes) {
            $codes[$item->name] = $item;
        });
        
        $this->info("Start parsing ICAL files");
        foreach ($event_sources as $i => $event_source) {
            if (!is_array($icalresponses[$i])) {
                $this->info("Find events in " . $event_source->name);
                file_put_contents("/tmp/" . $event_source->name . ".ical", $icalresponses[$i]); // FIXME: Remove
                $T->lap("Split on events for ".$event_source->name);
                if (preg_match_all('/BEGIN:VEVENT\r?\n(.+?)\r?\nEND:VEVENT/s', $icalresponses[$i], $icalmatches)) {
                    $this->info("Found ".(count($icalmatches[1])-1)." events");    
                    $T->lap("Fetch all uids so we can do a quick lookup ".$event_source->name);
                    $uids = [];
                    $event_source->events()->get(['uid', 'org_updated_at', 'updated_data_at'])->map(function($item) use(&$uids) {                        
                        if($item->org_updated_at > $item->updated_data_at) {
                            $uids[$item->uid] = $item->org_updated_at;
                        } else {
                            $uids[$item->uid] = $item->updated_data_at;
                        }
                    });
                    $deleted = $uids;
                    
                    // Loop over all the found events
                    $T->lap("Parse events for ".$event_source->name);
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
                                            if($timezone) {
                                                $event->org_start_date_timezone = $timezone;
                                            }
                                            break;
                                        case 'dtend':
                                            $event->end_date = $dt;
                                            if($timezone) {
                                                $event->org_end_date_timezone = $timezone;
                                            }
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

                                // Calculate number of hours 
                                $di = $event->end_date->diff($event->start_date);
                                $event->hours = $di->h + $di->i / 60;

                                // Copy data to new object
                                $event->org_data = $eventstr;
                                $event->summary = $title;
                                if(isset($data['description'])) {
                                    $event->description = $data['description'];
                                }
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
                                
                                // Check for events we already have
                                if (isset($uids[$event->uid])) {
                                    // Remove from deleted list
                                    unset($deleted[$event->uid]);
                                    
                                    // See if the event has been updated compared to the one in the database
                                    $db_event_org_updated_at = new DateTime($uids[$event->uid], new DateTimeZone('UTC'));
                                    
                                    // Check if same or older version of new event so skip
                                    if($event->org_updated_at <= $db_event_org_updated_at) {
                                        // SKIP
 
                                    // Updated version of the event
                                    } else {
                                        // Get the old event from the database
                                        /* @var $db_event TimesheetEvent */
                                        $db_event = $event_source->events()->where('uid', $event->uid)->firstOrFail();
                                        $changes = $db_event->toChangesArray($event);
                                        
                                        // Make sure it's more than the org_updated_at that has been changed
                                        if (count($changes) > 1) {
                                            // Check if we have changed the event in the database or used it in a timesheet
                                            if ($db_event->manualedit || $db_event->timesheet) {
                                                $this->info("Updated Data");
                                                $db_event->updated_data = $event->org_data;
                                                $db_event->updated_data_at = $event->org_updated_at;
                                                
                                            // Update the db_event with the changes
                                            } else {
                                                $this->info("Updated Event");
                                                foreach ($changes as $key => $value) {
                                                    if($value == null) {
                                                        unset($db_event->$key);
                                                    } else {
                                                        $db_event->$key = $value;
                                                    }
                                                }
                                            }
                                            
                                        } else {
                                            $this->info("Nothing Changed");
                                            // Nothing has been changed so update the org_updated_at
                                            $db_event->org_updated_at = $changes['org_updated_at'];
                                        }
                                        $db_event->save();
                                    }
                                    
                                } else {
                                    try {
                                        $this->info("New event: " . $event->summary);
                                        $event->save();
                                        
                                    } catch (Exception $ex) {
                                        echo "'" . $event->summary . "'\n";
                                        var_dump($data);
                                        echo $ex->getMessage();
                                        echo $ex->getTraceAsString();
                                        exit(0);
                                    }
                                }
                                // Add new uid to know uids
                                $uids[$event->uid] = $event->org_updated_at;
                            }
                        }
                    }
                    $this->info("Deleted ".count($deleted). " events");
                    foreach($deleted as $uid => $lastupdated_date) {
                        // FIXME: Delete events in database
                        echo "$uid: $lastupdated_date\n";
                    }
                    
                } else {
                    // Parse error
                }
                
            } else {
                // Curl Error
            }
        }

        foreach($T->end()['laps'] as $lap) {
            echo number_format($lap['total'], 3)." : {$lap['name']}\n";
        }
        
        $this->info('Done');
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
