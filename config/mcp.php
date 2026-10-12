<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

return [
    'enabled' => env('MCP_ENABLED', false),
    'allowed_origins' => array_filter(explode(',', env('MCP_ALLOWED_ORIGINS', ''))),

    // Additional restrictions only. Keys are "company_id:user_id", using internal IDs.
    // Values are tool names, or "tool.operation". The REST policies still apply.
    'disabled_tools' => [],
    'max_response_bytes' => 1048576,
];
