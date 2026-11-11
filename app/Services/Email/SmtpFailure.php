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

namespace App\Services\Email;

class SmtpFailure
{
    public const RETRY = 'retry';

    public const FALLBACK = 'fallback';

    public const FAIL = 'fail';

    public function action(\Throwable $e, int $attempt, int $tries): string
    {
        $code = (int) $e->getCode();
        $message = $e->getMessage();

        if (in_array($code, [550, 551, 552, 553, 554], true)) {
            return self::FAIL;
        }

        if (
            in_array($code, [504, 530, 534, 535], true)
            || str_contains($message, 'Failed to authenticate')
            || str_contains($message, 'Failed to find an authenticator')
            || stripos($message, 'starttls') !== false
            || str_contains($message, 'TLS required')
            || stripos($message, 'dsn') !== false
        ) {
            return self::FALLBACK;
        }

        if ($attempt < $tries) {
            return self::RETRY;
        }

        return self::FALLBACK;
    }
}
