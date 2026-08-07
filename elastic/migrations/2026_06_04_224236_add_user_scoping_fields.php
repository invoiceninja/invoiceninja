<?php
declare(strict_types=1);

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use function Elastic\Migrations\prefix_index_name;

final class AddUserScopingFields implements MigrationInterface
{
    /**
     * Indices whose documents carry user_id/assigned_user_id. Contacts inherit
     * both values from their parent client/vendor (see ClientContact and
     * VendorContact toSearchableArray), so they need the same fields.
     *
     * @var string[]
     */
    private const SEARCH_INDICES = [
        'clients',
        'invoices',
        'quotes',
        'credits',
        'recurring_invoices',
        'expenses',
        'vendors',
        'purchase_orders',
        'projects',
        'tasks',
        'client_contacts',
        'vendor_contacts',
    ];

    /**
     * @var array<string, array{type: string}>
     */
    private const USER_SCOPING_PROPERTIES = [
        'user_id' => ['type' => 'keyword'],
        'assigned_user_id' => ['type' => 'keyword'],
    ];

    /**
     * Run the migration.
     *
     * Selectively adds the keyword scoping fields ONLY to indices where they are
     * absent. Fields that already exist are left untouched — Elasticsearch cannot
     * change a mapped field from text to keyword in place, so re-declaring an
     * existing (dynamically text-mapped) field would throw a 400. A field already
     * present as text still satisfies the SearchController term filter for the
     * integer-string ids it stores.
     *
     * This does NOT backfill existing documents. Documents indexed before this
     * migration have no user_id/assigned_user_id, so the SearchController
     * permission filter will (fail-closed) exclude them for restricted users
     * until a full reindex runs:
     *   php artisan elastic:import-all   (or scout:queue-import per model)
     */
    public function up(): void
    {
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));

        foreach (self::SEARCH_INDICES as $index) {
            $prefixedIndex = prefix_index_name($index);

            if ($client->indices()->exists(['index' => $prefixedIndex])->getStatusCode() !== 200) {
                continue;
            }

            $mapping = $client->indices()->getMapping(['index' => $prefixedIndex])->asArray();

            $missing = self::missingMappingProperties(
                self::propertiesForIndexMapping($mapping, $index),
                self::USER_SCOPING_PROPERTIES
            );

            if ($missing === []) {
                continue;
            }

            Index::putMappingRaw($index, [
                'properties' => $missing,
            ]);
        }
    }

    /**
     * Resolve the property map for an index from a raw getMapping() response,
     * tolerating both prefixed and unprefixed index keys.
     *
     * @param  array<string, mixed> $mapping
     * @return array<string, mixed>
     */
    public static function propertiesForIndexMapping(array $mapping, string $index): array
    {
        $prefixedIndex = prefix_index_name($index);

        return $mapping[$prefixedIndex]['mappings']['properties']
            ?? $mapping[$index]['mappings']['properties']
            ?? [];
    }

    /**
     * Return only the required properties that are not already mapped, regardless
     * of their existing type. Existing fields are never redefined.
     *
     * @param  array<string, mixed> $existingProperties
     * @param  array<string, mixed> $requiredProperties
     * @return array<string, mixed>
     */
    public static function missingMappingProperties(array $existingProperties, array $requiredProperties): array
    {
        $missing = [];

        foreach ($requiredProperties as $field => $definition) {
            if (! array_key_exists($field, $existingProperties)) {
                $missing[$field] = $definition;
            }
        }

        return $missing;
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        //
    }
}
