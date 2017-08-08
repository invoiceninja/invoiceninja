<?php

namespace App\Http\ViewComposers;

use Illuminate\View\View;

class AppLanguageComposer
{
    /**
     * Bind data to the view.
     *
     * @param View $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('appLanguage', $this->getLanguage());
    }

    /**
     * Get the language from the current locale.
     *
     * @return string
     */
    private function getLanguage()
    {
        $code = app()->getLocale();

        if (preg_match('/_/', $code)) {
            $codes = explode('_', $code);
            $code = $codes[0];
        }

        return $code;
    }
}
