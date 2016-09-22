<?php

namespace App\Http\ViewComposers;

use Cache;
use Illuminate\View\View;

/**
 * ClientPortalHeaderComposer.php.
 *
 * @copyright See LICENSE file that was distributed with this source code.
 */
class ClientPortalHeaderComposer
{
    /**
     * Bind data to the view.
     *
     * @param  View  $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('testing', 'value');
    }
}
