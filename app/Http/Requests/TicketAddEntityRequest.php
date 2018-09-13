<?php

namespace App\Http\Requests;

use App\Models\TicketRelation;

class TicketAddEntityRequest extends EntityRequest
{
    protected $entityType = ENTITY_TICKET;

    public function authorize()
    {
        return $this->user()->can('edit', Ticket::class);
    }


    public function addEntity()
    {
        $entityType = request()->entity;
        $linkEntity = request()->entity;

        if(request()->entity == 'quote')
            $entityType = 'invoice';

        $className = '\App\Models\\'.ucfirst($entityType);
        $entityModel = new $className();

        $entityId = $entityModel::getPortalPrivateId(request()->entity_id, request()->account_id);

        $tr = new TicketRelation();
        $tr->entity = $linkEntity;
        $tr->entity_id = $entityId;
        $tr->ticket_id = request()->ticket_id;
        $tr->save();

        $str = self::buildEntityUrl($linkEntity, request()->entity_id, request()->account_id);
        $str .= ' <i style="margin-left:5px;width:12px;cursor:pointer" onclick="removeRelation('.$tr->id.')" class="fa fa-minus-circle redlink" title="Remove item"/>';

        $tr->entity_url = $str;
        $tr->save();

        return $tr;
    }

    private static function buildEntityUrl($entityType, $publicId, $accountId) : string
    {

        $linkEntity = $entityType;

        if($entityType == 'quote')
            $entityType = 'invoice';

        $className = '\App\Models\\'.ucfirst($entityType);
        $entityModel = new $className();
        $entity = $entityModel::scope($publicId, $accountId)->first();

        return link_to("{$linkEntity}s/{$publicId}/edit", self::setLinkDescription($linkEntity, $entity), ['class' => ''])->toHtml();

    }



    private static function setLinkDescription($entityType, $entity)
    {
        switch($entityType)
        {
            case 'quote':
                return trans('texts.quote'). ' ' .$entity->invoice_number;

            case 'invoice':
                return trans('texts.invoice'). ' ' .$entity->invoice_number;

            case 'task':
                return trans('texts.task'). ' ' .$entity->description;

            case 'payment':
                return trans('texts.payment'). '('. trans('texts.invoice') . ' #'. $entity->invoice->invoice_number. ')';

            case 'credit':
                return trans('texts.credit'). ' (' .$entity->client->getDisplayName(). ' ' .$entity->amount.')';

            case 'expense':
                return strlen($entity->public_notes) ? trans('texts.expense'). ' ' .$entity->public_notes : trans('texts.expense'). ' ' .$entity->amount;

            case 'project':
                return $entity->name;

            default:
                return '';

        }
    }
}