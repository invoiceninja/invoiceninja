<?php

namespace App\Models\Traits;

/**
 * Class HasCustomMessages.
 */
trait HasCustomMessages
{
    /**
     * @param $value
     */
    public function setCustomMessagesAttribute($data)
    {
        $fields = [];

        if (! is_array($data)) {
            $data = json_decode($data);
        }

        foreach ($data as $key => $value) {
            if ($value) {
                $fields[$key] = $value;
            }
        }

        $this->attributes['custom_messages'] = count($fields) ? json_encode($fields) : null;
    }

    public function getCustomMessagesAttribute($value)
    {
        return json_decode($value ?: '{}');
    }

    public function customMessage($type)
    {
        $messages = $this->custom_messages;

        if (! empty($messages->$type)) {
            return $messages->$type;
        }

        if ($this->account) {
            return $this->account->customMessage($type);
        } else {
            return '';
        }
    }
}
