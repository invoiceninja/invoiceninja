<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Socket;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Class DownloadAvailable.
 */
class DownloadAvailable implements ShouldBroadcast
{
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public string $url, public string $message, public User $user) {}

    public static function buildMessage(string $contentLabel): string
    {
        return ctrans('texts.download_ready', ['message' => $contentLabel]).' '.ctrans('texts.download_timeframe');
    }

    public static function notify(User $user, string $url, string $contentLabel): void
    {
        broadcast(new self($url, self::buildMessage($contentLabel), $user));
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user-{$this->user->account->key}-{$this->user->hashed_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
