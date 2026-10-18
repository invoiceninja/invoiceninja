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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;

class ValidateProtectedDownloadSignature
{
    /**
     * @var array<int, string>
     */
    protected array $ignore = [
        'q',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('filesystems.protected_download_allow_unsigned')) {
            return $next($request);
        }

        if ($request->hasValidSignatureWhileIgnoring($this->ignore, absolute: false)) {
            return $next($request);
        }

        throw new InvalidSignatureException();
    }
}
