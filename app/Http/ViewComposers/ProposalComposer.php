<?php

namespace App\Http\ViewComposers;

use Illuminate\View\View;
use App\Models\ProposalSnippet;
use App\Models\Document;

/**
 * ClientPortalHeaderComposer.php.
 *
 * @copyright See LICENSE file that was distributed with this source code.
 */
class ProposalComposer
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
        $snippets = ProposalSnippet::scope()
            ->with('proposal_category')
            ->orderBy('name')
            ->get();

        $view->with('snippets', $snippets);


        $documents = Document::scope()
            ->whereNull('invoice_id')
            ->whereNull('expense_id')
            ->get();

        $data = [];
        foreach ($documents as $document) {
            $data[] = [
                'src' => $document->getProposalUrl(),
                'public_id' => $document->public_id,
            ];
        }

        $view->with('documents', $data);
    }
}
