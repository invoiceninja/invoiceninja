<?php

	return [
    'client' => [
        'notes' => function ($self) {
	            return $self->hasMany('Modules\Notes\Entities\Note');
       }
    ],
];