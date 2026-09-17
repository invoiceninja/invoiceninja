<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[CoversNothing]
class LivewireCheckboxBindingTest extends TestCase
{
    public function testCheckboxesDoNotUseLiveModelBinding(): void
    {
        $matches = [];

        foreach ($this->bladeFiles(dirname(__DIR__, 2) . '/resources/views') as $file) {
            $contents = file_get_contents($file->getPathname());

            $this->assertIsString($contents);

            preg_match_all(
                '/<input\b(?=[^>]*\btype\s*=\s*["\']checkbox["\'])(?=[^>]*\bwire:model\.live(?:[.\w-]*)?\s*=)[^>]*>/i',
                $contents,
                $found
            );

            foreach ($found[0] as $input) {
                $matches[] = $this->relativePath($file->getPathname()) . ': ' . trim($input);
            }
        }

        $this->assertSame(
            [],
            $matches,
            "Checkboxes should avoid wire:model.live so they remain compatible with Safari 13.\n" . implode("\n", $matches)
        );
    }

    public function testPortalBulkActionButtonsWaitForPaginationRequests(): void
    {
        $target = 'wire:target="toggleSelected, toggleSelectAll, toggleStatus, per_page, sortBy, previousPage, gotoPage, nextPage"';

        foreach ([
            'resources/views/portal/ninja2020/components/livewire/invoices-table.blade.php' => 2,
            'resources/views/portal/ninja2020/components/livewire/quotes-table.blade.php' => 3,
            'resources/views/portal/ninja2020/components/livewire/purchase-orders-table.blade.php' => 1,
        ] as $path => $expected_count) {
            $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);

            $this->assertIsString($contents);
            $this->assertSame(
                $expected_count,
                substr_count($contents, $target),
                "{$path} is missing pagination loading targets on one or more bulk action buttons."
            );
        }
    }

    public function testPortalBulkSelectionCheckboxesHaveStateKeys(): void
    {
        foreach ([
            'resources/views/portal/ninja2020/components/livewire/invoices-table.blade.php' => [
                'wire:key="invoice-status-paid-',
                'wire:key="invoice-status-unpaid-',
                'wire:key="invoice-status-overdue-',
                'wire:key="invoice-select-all-',
                'wire:key="invoice-checkbox-{{ $invoice->hashed_id }}-',
            ],
            'resources/views/portal/ninja2020/components/livewire/quotes-table.blade.php' => [
                'wire:key="quote-status-sent-',
                'wire:key="quote-status-approved-',
                'wire:key="quote-status-expired-',
                'wire:key="quote-status-rejected-',
                'wire:key="quote-select-all-',
                'wire:key="quote-checkbox-{{ $quote->hashed_id }}-',
            ],
            'resources/views/portal/ninja2020/components/livewire/purchase-orders-table.blade.php' => [
                'wire:key="purchase-order-status-sent-',
                'wire:key="purchase-order-status-accepted-',
                'wire:key="purchase-order-select-all-',
                'wire:key="purchase-order-checkbox-{{ $purchase_order->hashed_id }}-',
            ],
        ] as $path => $expected_keys) {
            $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);

            $this->assertIsString($contents);

            foreach ($expected_keys as $expected_key) {
                $this->assertStringContainsString(
                    $expected_key,
                    $contents,
                    "{$path} is missing state key {$expected_key}."
                );
            }
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function bladeFiles(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file;
            }
        }
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace(dirname(__DIR__, 2), '', $path), '/');
    }
}
