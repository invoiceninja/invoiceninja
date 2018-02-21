<?php

namespace App\Http\Requests;

use App\Models\ProposalCategory;

class ProposalSnippetRequest extends EntityRequest
{
    protected $entityType = ENTITY_PROPOSAL_SNIPPET;

    public function sanitize()
    {
        $input = $this->all();

        // check if we're creating a new proposal category
        if ($this->proposal_category_id == '-1') {
            $data = [
                'name' => trim($this->proposal_category_name)
            ];
            if (ProposalCategory::validate($data) === true) {
                $category = app('App\Ninja\Repositories\ProposalCategoryRepository')->save($data);
                $input['proposal_category_id'] = $category->id;
            } else {
                $input['proposal_category_id'] = null;
            }
        } elseif ($this->proposal_category_id) {
            $input['proposal_category_id'] = ProposalCategory::getPrivateId($this->proposal_category_id);
        }

        $this->replace($input);

        return $this->all();
    }
}
