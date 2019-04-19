<?php

namespace App\Observers;

use App\Models\Proposal;

class ProposalObserver
{
    /**
     * Handle the proposal "created" event.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return void
     */
    public function created(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "updated" event.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return void
     */
    public function updated(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "deleted" event.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return void
     */
    public function deleted(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "restored" event.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return void
     */
    public function restored(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "force deleted" event.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return void
     */
    public function forceDeleted(Proposal $proposal)
    {
        //
    }
}
