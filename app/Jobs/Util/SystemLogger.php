<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SystemLogger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $log;

    protected $category_id;

    protected $event_id;

    protected $type_id;

    protected $client;

    protected $company;

    public function __construct($log, $category_id, $event_id, $type_id, ?Client $client, Company $company)
    {
        $this->log = $log;
        $this->category_id = $category_id;
        $this->event_id = $event_id;
        $this->type_id = $type_id;
        $this->client = $client;
        $this->company = $company;
    }

    public function handle() :void
    {
        if(!$this->company)
            return;

        MultiDB::setDb($this->company->db);

        $client_id = $this->client ? $this->client->id : null;
        $user_id = $this->client ? $this->client->user_id : $this->company->owner()->id;

        $sl = [
            'client_id' => $client_id,
            'company_id' => $this->company->id,
            'user_id' => $user_id,
            'log' => $this->log,
            'category_id' => $this->category_id,
            'event_id' => $this->event_id,
            'type_id' => $this->type_id,
        ];

        if(!$this->log)
            return;

        SystemLog::create($sl);
    }
}
