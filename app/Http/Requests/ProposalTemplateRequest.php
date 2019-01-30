<?php

namespace App\Http\Requests;

/**
 * Class ProposalTemplateRequest
 * @package App\Http\Requests
 */
class ProposalTemplateRequest extends EntityRequest
{
    /**
     * @var string
     */
    protected $entityType = ENTITY_PROPOSAL_TEMPLATE;

    /**
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('view', ENTITY_PROPOSAL) || $this->user()->can('createEntity', ENTITY_PROPOSAL);
    }
}
