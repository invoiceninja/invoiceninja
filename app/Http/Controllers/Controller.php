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

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Proxy method for rendering views.
     *
     * @param string $path
     * @param array $options
     *
     * @return Factory|View
     */
    public function render(string $path, array $options = [])
    {
        return render($path, $options);
    }

    /**
     * Run a callback under a temporary execution limit, then restore the previous one.
     *
     * set_time_limit() applies to the whole PHP process. HTTP tests share that process,
     * so a limit left in place kills whatever test happens to be running when it expires.
     */
    protected function withinTimeLimit(int $seconds, callable $callback): mixed
    {
        $previous_limit = (int) ini_get('max_execution_time');

        set_time_limit($seconds);

        try {
            return $callback();
        } finally {
            set_time_limit($previous_limit);
        }
    }
}
