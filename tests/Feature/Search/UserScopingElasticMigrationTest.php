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

namespace Tests\Feature\Search;

use Tests\TestCase;

require_once __DIR__ . '/../../../elastic/migrations/2026_06_04_224236_add_user_scoping_fields.php';

class UserScopingElasticMigrationTest extends TestCase
{
    public function testExistingTextFieldsAreNotRedefinedAsKeywords(): void
    {
        $existingProperties = [
            'user_id' => ['type' => 'keyword'],
            'assigned_user_id' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword', 'ignore_above' => 256],
                ],
            ],
        ];

        $requiredProperties = [
            'user_id' => ['type' => 'keyword'],
            'assigned_user_id' => ['type' => 'keyword'],
        ];

        $this->assertSame(
            [],
            \AddUserScopingFields::missingMappingProperties($existingProperties, $requiredProperties)
        );
    }

    public function testOnlyAbsentScopingFieldsAreReturnedForPutMapping(): void
    {
        $existingProperties = [
            'user_id' => ['type' => 'keyword'],
        ];

        $requiredProperties = [
            'user_id' => ['type' => 'keyword'],
            'assigned_user_id' => ['type' => 'keyword'],
        ];

        $this->assertSame(
            ['assigned_user_id' => ['type' => 'keyword']],
            \AddUserScopingFields::missingMappingProperties($existingProperties, $requiredProperties)
        );
    }

    public function testPropertiesCanBeReadFromPrefixedIndexMappings(): void
    {
        config(['elastic.migrations.prefixes.index' => 'testing_']);

        $properties = [
            'assigned_user_id' => ['type' => 'text'],
        ];

        $mapping = [
            'testing_clients' => [
                'mappings' => [
                    'properties' => $properties,
                ],
            ],
        ];

        $this->assertSame(
            $properties,
            \AddUserScopingFields::propertiesForIndexMapping($mapping, 'clients')
        );
    }
}
