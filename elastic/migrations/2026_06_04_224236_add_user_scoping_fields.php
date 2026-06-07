<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class AddUserScopingFields implements MigrationInterface
{
    /**
     * Run the migration.
     *
     * putMapping only registers the keyword fields on each index; it does NOT
     * backfill existing documents. Documents indexed before this migration have
     * no user_id/assigned_user_id, so the SearchController permission filter will
     * (fail-closed) exclude them for restricted users until a full reindex runs.
     *
     * After deploying, reindex every affected model, e.g.:
     *   php artisan elastic:import-all   (or scout:queue-import per model)
     */
    public function up(): void
    {
        foreach (['clients','invoices','quotes','credits','recurring_invoices',
          'expenses','vendors','purchase_orders','projects','tasks'] as $index) {
            Index::putMapping($index, static function (Mapping $mapping): void {
                $mapping->keyword('user_id');
                $mapping->keyword('assigned_user_id');
            });
        }
        // contacts: user_id only
        foreach (['client_contacts','vendor_contacts'] as $index) {
            Index::putMapping($index, static function (Mapping $mapping): void {
                $mapping->keyword('user_id');
                $mapping->keyword('assigned_user_id'); // see note below — index parent's value
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        //
    }
}
