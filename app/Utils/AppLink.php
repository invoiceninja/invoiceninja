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

namespace App\Utils;

use App\Models\BaseModel;

/**
 * The server's own record links — invoiceninja/flutter#144.
 *
 * `/app/<root>/<id>` resolves per recipient: the app when it is installed, the
 * bridge page's web client when it is not. Two rules are invisible at a call
 * site — no `/edit` suffix (the apps have detail screens, and React gets its
 * suffix at the bridge from {@see AppLinkPath}), and on hosted `APP_URL` must be
 * the host the apps claim or the OS will not open the link.
 */
class AppLink
{
    /**
     * @param  string  $appRoot  The app's route root, e.g. `invoices`.
     */
    public static function forRecord(string $appRoot, BaseModel $record): string
    {
        $link = rtrim((string) config('ninja.app_url'), '/').'/app/'.$appRoot.'/'.$record->hashed_id;
        $company = $record->company?->hashed_id;

        // Without it the record opens against whatever workspace is active.
        return $company ? $link.'?company='.$company : $link;
    }
}
