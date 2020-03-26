<?php

namespace App\Jobs;

use App\Jobs\Job;
use Postmark\PostmarkClient;
use stdClass;

class LoadPostmarkHistory extends Job
{
    public function __construct($email)
    {
        $this->email = $email;
        $this->bounceId = false;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $str = '';

        if (config('services.postmark')) {
            $this->account = auth()->user()->account;
            $this->postmark = new PostmarkClient(config('services.postmark'));

            $str .= $this->loadBounceEvents();
            $str .= $this->loadEmailEvents();
        }

        if (! $str) {
            $str = trans('texts.no_messages_found');
        }

        $response = new stdClass;
        $response->str = $str;
        $response->bounce_id = $this->bounceId;

        return $response;
    }

    private function loadBounceEvents() {
        $str = '';
        $response = $this->postmark->getBounces(5, 0, null, null, $this->email, $this->account->account_key);

        foreach ($response['bounces'] as $bounce) {
            if (! $bounce['inactive'] || ! $bounce['canactivate']) {
                continue;
            }

            $str .= sprintf('<b>%s</b><br/>', $bounce['subject']);
            $str .= sprintf('<span class="text-danger">%s</span> | %s<br/>', $bounce['type'], $this->account->getDateTime($bounce['bouncedat'], true));
            $str .= sprintf('<span class="text-muted">%s %s</span><p/>', $bounce['description'], $bounce['details']);

            $this->bounceId = $bounce['id'];
        }

        return $str;
    }

    private function loadEmailEvents() {
        $str = '';
        $response = $this->postmark->getOutboundMessages(5, 0, $this->email, null, $this->account->account_key);

        foreach ($response['messages'] as $message) {
            $details = $this->postmark->getOutboundMessageDetails($message['MessageID']);
            $str .= sprintf('<b>%s</b><br/>', $details['subject']);

            $event = $details['messageevents'][0];
            $str .= sprintf('%s | %s<br/>', $event['Type'], $this->account->getDateTime($event['ReceivedAt'], true));
            if ($message = $event['Details']['DeliveryMessage']) {
                $str .= sprintf('<span class="text-muted">%s</span><br/>', $message);
            }
            if ($server = $event['Details']['DestinationServer']) {
                $str .= sprintf('<span class="text-muted">%s</span><br/>', $server);
            }

            /*
            if (count($details['messageevents'])) {
                $event = $details['messageevents'][0];
                $str .= sprintf('%s | %s<br/>', $event['Type'], $this->account->getDateTime($event['ReceivedAt'], true));
                if ($message = $event['Details']['DeliveryMessage']) {
                    $str .= sprintf('<span class="text-muted">%s</span><br/>', $message);
                }
                if ($server = $event['Details']['DestinationServer']) {
                    $str .= sprintf('<span class="text-muted">%s</span><br/>', $server);
                }
            } else {
                $str .= trans('texts.processing') . '...';
            }
            */

            $str .= '<p/>';
        }

        return $str;
    }
}
