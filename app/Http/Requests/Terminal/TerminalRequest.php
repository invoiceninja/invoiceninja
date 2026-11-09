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

namespace App\Http\Requests\Terminal;

use App\Exceptions\TerminalException;
use App\Http\Requests\Request;
use App\Utils\Ninja;
use Illuminate\Auth\Access\AuthorizationException;

class TerminalRequest extends Request
{
    protected string $error_message = '';

    protected ?string $error_code = null;

    /* The platform switch, and Pro or Enterprise on hosted */
    protected function terminalAvailable(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!config('ninja.terminal_enabled')) {
            $this->error_code = 'disabled';

            return false;
        }

        if (Ninja::isHosted() && $user->account->isFreeHostedClient()) {
            $this->error_code = 'plan_required';

            return false;
        }

        return true;
    }

    protected function failedAuthorization()
    {
        if ($this->error_code) {
            throw new TerminalException($this->error_code, $this->error_code == 'disabled' ? 503 : 403);
        }

        throw new AuthorizationException($this->error_message ?: ctrans('texts.authorization_failure'));
    }
}
