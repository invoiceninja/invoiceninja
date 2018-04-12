<?php

namespace App\Ninja\Presenters;

class UserPresenter extends EntityPresenter
{
    public function email()
    {
        return htmlentities(sprintf('%s <%s>', $this->fullName(), $this->entity->email));
    }

    public function fullName()
    {
        return $this->entity->first_name . ' ' . $this->entity->last_name;
    }

    public function statusCode()
    {
        $status = '';
        $user = $this->entity;
        $account = $user->account;

        if ($user->confirmed) {
            $status .= 'C';
        } elseif ($user->registered) {
            $status .= 'R';
        } else {
            $status .= 'N';
        }

        if ($account->isTrial()) {
            $status .= 'T';
        } elseif ($account->isEnterprise()) {
            $status .= 'E';
        } elseif ($account->isPro()) {
            $status .= 'P';
        } else {
            $status .= 'H';
        }

        return $status;
    }
}
