<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

trait WithBulkSelection
{
    /** @var array<int, string> */
    public array $selected = [];

    public bool $select_all = false;

    public function updatingPage(): void
    {
        $this->resetSelection();
    }

    public function updatedPerPage(): void
    {
        $this->resetSelection();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? $this->selectablePageIds() : [];
    }

    public function updatedSelected(): void
    {
        $this->select_all = false;
    }

    /**
     * Selection is scoped to the rows currently visible on the page.
     * Any change that alters which rows are visible (pagination, per_page,
     * filter, sort) must clear it — otherwise a user could bulk-act on
     * rows they can no longer see and verify.
     */
    protected function resetSelection(): void
    {
        $this->selected = [];
        $this->select_all = false;
    }

    /**
     * Hashed IDs of the items rendered on the current page.
     *
     * @return array<int, string>
     */
    abstract protected function selectablePageIds(): array;
}
