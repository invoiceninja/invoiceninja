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

namespace App\Services\Download;

use Illuminate\Support\Carbon;

class ProtectedDownloadResult
{
    public function __construct(
        public string $url,
        public string $hash,
        public string $storage_path,
        public Carbon $expires_at,
    ) {
    }
}
