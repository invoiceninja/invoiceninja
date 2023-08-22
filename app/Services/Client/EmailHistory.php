<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Client;

use App\Models\Client;
use App\Models\SystemLog;
use Postmark\PostmarkClient;
use App\Services\AbstractService;

class EmailHistory extends AbstractService
{
    private string $postmark_token;

    private PostmarkClient $postmark;

    private array $default_response = [
            'subject' => 'Message not found.',
            'status' => '',
            'recipient' =>  '',
            'type' => '',
            'subject' => '',
            'server' => '',
            'server_ip' => '',
        ];

    public function __construct(public Client $client)
    {
    }

    public function run(): array
    {
        // $settings = $this->client->getMergedSettings();
        
        // if($settings->email_sending_method == 'default'){
            // $this->postmark_token = config('services.postmark.token');
        // }
        // elseif($settings->email_sending_method == 'client_postmark'){
        //     $this->postmark_token = $settings->postmark_secret;
        // }
        // else{
        //     return [];
        // }

        // $this->postmark = new PostmarkClient($this->postmark_token);
            
        return SystemLog::query()
                        ->where('client_id', $this->client->id)
                        ->where('category_id', SystemLog::CATEGORY_MAIL)
                        ->orderBy('id','DESC')
                        ->cursor()
                        ->map(function ($system_log) {
                            
                            if($system_log->log['history'] ?? false){
                                return json_decode($system_log->log['history'],true);
                            }
                        })->toArray();
    }

    private function fetchMessage(string $message_id): array
    {
        if(strlen($message_id) < 1){
            return $this->default_response;
        } 
    
        try {
        
            $messageDetail = $this->postmark->getOutboundMessageDetails($message_id);
                
            return [
                'subject' => $messageDetail->subject ?? '',
                'status' => $messageDetail->status ?? '',
                'recipient' => $messageDetail->messageevents[0]['Recipient'] ?? '',
                'type' => $messageDetail->messageevents[0]->Type ?? '',
                'delivery_message' => $messageDetail->messageevents[0]->Details->DeliveryMessage ?? '',
                'server' => $messageDetail->messageevents[0]->Details->DestinationServer ?? '',
                'server_ip' => $messageDetail->messageevents[0]->Details->DestinationIP ?? '',
            ];
        
        }
        catch (\Exception $e) {

            return $this->default_response;

        }
    }
}