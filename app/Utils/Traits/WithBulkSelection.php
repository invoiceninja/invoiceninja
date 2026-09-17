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
        $page_ids = $this->selectablePageIds();

        $this->selected = $value ? $page_ids : [];
        $this->select_all = $value && $page_ids !== [];
    }

    public function updatedSelected(): void
    {
        $this->syncSelectAllState();
    }

    public function toggleSelected(string $id): void
    {
        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));
        } else {
            $this->selected[] = $id;
        }

        $this->selected = array_values(array_unique($this->selected));

        $this->syncSelectAllState();
    }

    public function toggleSelectAll(): void
    {
        $page_ids = $this->selectablePageIds();

        if ($this->select_all || $this->allPageIdsSelected($page_ids)) {
            $this->selected = [];
            $this->select_all = false;

            return;
        }

        $this->selected = $page_ids;
        $this->select_all = $page_ids !== [];
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
     * @param  array<int, string>  $page_ids
     */
    private function allPageIdsSelected(array $page_ids): bool
    {
        return $page_ids !== [] && array_diff($page_ids, $this->selected) === [];
    }

    private function syncSelectAllState(): void
    {
        $this->select_all = $this->allPageIdsSelected($this->selectablePageIds());
    }

    /**
     * Hashed IDs of the items rendered on the current page.
     *
     * @return array<int, string>
     */
    abstract protected function selectablePageIds(): array;
}
