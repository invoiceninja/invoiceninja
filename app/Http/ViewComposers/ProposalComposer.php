<?php

namespace App\Http\ViewComposers;

use Illuminate\View\View;
use App\Models\ProposalSnippet;

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
        $view->with('snippets', ProposalSnippet::scope()->with('proposal_category')->orderBy('name')->get());
    }
}
