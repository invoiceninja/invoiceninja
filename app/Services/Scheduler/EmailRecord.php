<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\Models\Invoice;
use App\Models\Scheduler;
use Illuminate\Support\Str;
use App\Utils\Traits\MakesHash;

class EmailRecord
{
    use MakesHash;

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {
        $class = 'App\\Models\\' . Str::camel($this->scheduler->parameters['entity']);

        $entity = $class::find($this->decodePrimaryKey($this->scheduler->parameters['entity_id']));

        if ($entity instanceof Invoice && $entity->company->verifactuEnabled() && !$entity->hasSentAeat()) {
            $entity->invitations()->update(['email_error' => 'primed']); // Flag the invitations as primed for AEAT submission
            $entity->service()->sendVerifactu();
        } elseif ($entity) {

            $template = $this->scheduler->parameters['template'] ?? $this->scheduler->parameters['entity'];

            $entity->service()->markSent()->sendEmail(email_type: $template);
        }

        $this->scheduler->forceDelete();
    }
}
