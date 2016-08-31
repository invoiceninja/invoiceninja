<?php namespace App\Libraries;

use Request;
use stdClass;
use Session;
use App\Models\EntityModel;

class HistoryUtils
{
    public static function trackViewed(EntityModel $entity)
    {
        if ($entity->isEntityType(ENTITY_CREDIT) || $entity->isEntityType(ENTITY_VENDOR)) {
            return;
        }

        $object =  static::convertToObject($entity);
        $history = Session::get(RECENTLY_VIEWED);
        $data = [];

        // Add to the list and make sure to only show each item once
        for ($i = 0; $i<count($history); $i++) {
            $item = $history[$i];

            if ($object->url == $item->url) {
                continue;
            }

            array_push($data, $item);

            if (isset($counts[$item->accountId])) {
                $counts[$item->accountId]++;
            } else {
                $counts[$item->accountId] = 1;
            }
        }

        array_unshift($data, $object);

        if (isset($counts[$entity->account_id]) && $counts[$entity->account_id] > RECENTLY_VIEWED_LIMIT) {
            array_pop($data);
        }

        //$data = [];
        Session::put(RECENTLY_VIEWED, $data);
    }

    private static function convertToObject($entity)
    {
        $object = new stdClass();
        $object->accountId = $entity->account_id;
        $object->url = $entity->present()->url;
        $object->entityType = $entity->subEntityType();
        $object->name = $entity->present()->titledName;
        $object->timestamp = time();

        if ($entity->isEntityType(ENTITY_CLIENT)) {
            $object->client_id = $entity->public_id;
            $object->client_name = $entity->getDisplayName();
        } elseif (method_exists($entity, 'client') && $entity->client) {
            $object->client_id = $entity->client->public_id;
            $object->client_name = $entity->client->getDisplayName();
        } else {
            $object->client_id = 0;
            $object->client_name = 0;
        }

        return $object;
    }

    public static function renderHtml()
    {
        $lastClientId = false;
        $clientMap = [];
        $str = '';
        $history = Session::get(RECENTLY_VIEWED, []);

        foreach ($history as $item)
        {
            if ($item->entityType == ENTITY_CLIENT && isset($clientMap[$item->client_id])) {
                continue;
            }

            $clientMap[$item->client_id] = true;

            if ($lastClientId === false || $item->client_id != $lastClientId)
            {
                $icon = '<i class="fa fa-users" style="width:30px"></i>';
                if ($item->client_id) {
                    $link = url('/clients/' . $item->client_id);
                    $name = $item->client_name ;

                    $buttonLink = url('/invoices/create/' . $item->client_id);
                    $button = '<a type="button" class="btn btn-primary btn-sm pull-right" style="margin-top:5px; margin-right:10px; text-indent:0px"
                                    href="' . $buttonLink . '">
                                    <i class="fa fa-plus-circle" style="width:20px" title="' . trans('texts.create_new') . '"></i>
                                </a>';
                } else {
                    $link = '#';
                    $name = trans('texts.unassigned');
                    $button = '';
                }

                $str .= sprintf('<li>%s<a href="%s"><div>%s %s</div></a></li>', $button, $link, $icon, $name);
                $lastClientId = $item->client_id;
            }

            if ($item->entityType == ENTITY_CLIENT) {
                continue;
            }

            $icon = '<i class="fa fa-' . EntityModel::getIcon($item->entityType . 's') . '"></i>';
            $str .= sprintf('<li style="text-align:right; padding-right:20px;"><a href="%s">%s %s</a></li>', $item->url, $item->name, $icon);
        }

        //dd($str);
        return $str;
    }
}
