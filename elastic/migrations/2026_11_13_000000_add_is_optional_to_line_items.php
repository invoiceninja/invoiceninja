<?php
declare(strict_types=1);

use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class AddIsOptionalToLineItems implements MigrationInterface
{
    /**
     * @var string[]
     */
    private const INDICES = [
        'invoices',
        'quotes',
        'credits',
        'recurring_invoices',
        'purchase_orders',
    ];

    /**
     * Add is_optional to the existing nested mappings without rebuilding them.
     */
    public function up(): void
    {
        foreach (self::INDICES as $index) {
            Index::putMappingRaw($index, [
                'properties' => [
                    'line_items' => [
                        'type' => 'nested',
                        'properties' => [
                            'is_optional' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * Elasticsearch cannot remove a mapped field in place.
     */
    public function down(): void
    {
        //
    }
}
