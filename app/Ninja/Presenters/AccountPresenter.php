<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

/**
 * Class AccountPresenter
 */
class AccountPresenter extends Presenter
{

    /**
     * @return mixed
     */
    public function name()
    {
        return $this->entity->name ?: trans('texts.untitled_account');
    }

    /**
     * @return string
     */
    public function website()
    {
        return Utils::addHttp($this->entity->website);
    }

    /**
     * @return mixed
     */
    public function currencyCode()
    {
        $currencyId = $this->entity->getCurrencyId();
        $currency = Utils::getFromCache($currencyId, 'currencies');
        return $currency->code;
    }
}