<?php

namespace App\Ninja\Datatables;

use URL;

class UserDatatable extends EntityDatatable
{
    public $entityType = ENTITY_USER;

    public function columns()
    {
        return [
            [
                'first_name',
                function ($model) {
                    return $model->public_id ? link_to('users/'.$model->public_id.'/edit', $model->first_name.' '.$model->last_name)->toHtml() : ($model->first_name.' '.$model->last_name);
                },
            ],
            [
                'email',
                function ($model) {
                    return $model->email;
                },
            ],
            [
                'confirmed',
                function ($model) {
                    if (! $model->public_id) {
                        return self::getStatusLabel(USER_STATE_OWNER);
                    } elseif ($model->deleted_at) {
                        return self::getStatusLabel(USER_STATE_DISABLED);
                    } elseif ($model->confirmed) {
                        if ($model->is_admin) {
                            return self::getStatusLabel(USER_STATE_ADMIN);
                        } else {
                            return self::getStatusLabel(USER_STATE_ACTIVE);
                        }
                    } else {
                        return self::getStatusLabel(USER_STATE_PENDING);
                    }
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.edit_user'),
                function ($model) {
                    return URL::to("users/{$model->public_id}/edit");
                },
                function ($model) {
                    return $model->public_id;
                },
            ],
            [
                uctrans('texts.send_invite'),
                function ($model) {
                    return URL::to("send_confirmation/{$model->public_id}");
                },
                function ($model) {
                    return $model->public_id && ! $model->confirmed;
                },
            ],
        ];
    }

    private function getStatusLabel($state)
    {
        $label = trans("texts.{$state}");
        $class = 'default';
        switch ($state) {
            case USER_STATE_PENDING:
                $class = 'default';
                break;
            case USER_STATE_ACTIVE:
                $class = 'info';
                break;
            case USER_STATE_DISABLED:
                $class = 'warning';
                break;
            case USER_STATE_OWNER:
                $class = 'success';
                break;
            case USER_STATE_ADMIN:
                $class = 'primary';
                break;
        }

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
}
