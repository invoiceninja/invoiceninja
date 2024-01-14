<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use Nwidart\Modules\Facades\Module;

/**
 * Class MakesMenu.
 */
trait MakesMenu
{
    /**
     * Builds an array of available modules for this view.
     * @param  string $entity Class name
     * @return array of modules
     */
    public function makeEntityTabMenu(string $entity): array
    {
        $tabs = [];

        foreach (Module::getCached() as $module) {
            if (! $module['sidebar']
                && $module['active'] == 1
                && in_array(strtolower(class_basename($entity)), $module['views'])) {
                $tabs[] = $module;
            }
        }

        return $tabs;
    }

    /**
     * Builds an array items to be presented on the sidebar.
     * @return void menu items
     */
    public function makeSideBarMenu()
    {
    }
}
