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

namespace App\Events\Socket;

use App\Models\User;
use League\Fractal\Manager;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Utils\Traits\Invoice\Broadcasting\DefaultResourceBroadcast;

/**
 * Class RefetchEntity.
 */
class RefetchEntity implements ShouldBroadcast
{
    use SerializesModels;
    use InteractsWithSockets;

    public function __construct(public string $entity, public ?string $entity_id, public User $user)
    {
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel("user-{$this->user->account->key}-{$this->user->id}"),
        ];
    }

    public function broadcastWith(): array
    {

        return [
            'entity' => $this->entity,
            'entity_id' => $this->entity_id,
        ];
    }

}
