<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Casts\QuickbooksSettingsCast;
use App\DataMapper\QuickbooksSettings;
use App\DataMapper\QuickbooksSync;
use App\DataMapper\QuickbooksSyncMap;
use App\Enum\SyncDirection;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Test QuickbooksSettings serialization to verify the fix for enum and nested object serialization.
 */
class QuickbooksSettingsSerializationTest extends TestCase
{

    /**
     * Test that demonstrates toArray() provides explicit control over enum serialization.
     * 
     * While get_object_vars() + json_encode() works (json_encode handles enums),
     * toArray() provides explicit, controlled serialization that's more maintainable.
     */
    public function testToArrayProvidesExplicitEnumSerialization()
    {
        $syncMap = new QuickbooksSyncMap([
            'direction' => SyncDirection::PULL->value,
        ]);

        // Using toArray() - explicit and controlled
        $array = $syncMap->toArray();
        $json = json_encode($array);
        $decoded = json_decode($json, true);

        // Verify explicit serialization works correctly
        $this->assertIsString($decoded['direction']);
        $this->assertEquals('pull', $decoded['direction']);
        
        // toArray() explicitly converts enum to string value
        $this->assertIsString($array['direction'], 
            'toArray() explicitly returns enum as string value');
    }

    /**
     * Test that the new toArray() method properly serializes enums.
     */
    public function testNewSerializationMethodWorksWithEnums()
    {
        $settings = new QuickbooksSettings([
            'accessTokenKey' => 'test_token',
            'refresh_token' => 'refresh_token',
            'realmID' => '123456',
            'accessTokenExpiresAt' => 1234567890,
            'refreshTokenExpiresAt' => 1234567890,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::PULL->value,
                ],
                'invoice' => [
                    'direction' => SyncDirection::PUSH->value,
                ],
                'product' => [
                    'direction' => SyncDirection::BIDIRECTIONAL->value,
                ],
            ],
        ]);

        // Use the new toArray() method
        $array = $settings->toArray();
        $json = json_encode($array);
        $decoded = json_decode($json, true);

        // Verify enum values are properly serialized as strings
        $this->assertIsString($decoded['settings']['client']['direction']);
        $this->assertEquals('pull', $decoded['settings']['client']['direction']);
        
        $this->assertIsString($decoded['settings']['invoice']['direction']);
        $this->assertEquals('push', $decoded['settings']['invoice']['direction']);
        
        $this->assertIsString($decoded['settings']['product']['direction']);
        $this->assertEquals('bidirectional', $decoded['settings']['product']['direction']);
    }

    /**
     * Test that nested objects are properly serialized.
     */
    public function testNestedObjectsAreProperlySerialized()
    {
        $settings = new QuickbooksSettings([
            'accessTokenKey' => 'test_token',
            'refresh_token' => 'refresh_token',
            'realmID' => '123456',
            'accessTokenExpiresAt' => 1234567890,
            'refreshTokenExpiresAt' => 1234567890,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::PULL->value,
                ],
               
            ],
        ]);

        $array = $settings->toArray();

        // Verify nested QuickbooksSync structure
        $this->assertIsArray($array['settings']);
        $this->assertArrayHasKey('client', $array['settings']);
        $this->assertArrayHasKey('income_account_map', $array['settings']);

        // Verify nested QuickbooksSyncMap structure
        $this->assertIsArray($array['settings']['client']);
        $this->assertArrayHasKey('direction', $array['settings']['client']);
        $this->assertEquals('pull', $array['settings']['client']['direction']);
    }

    /**
     * Test round-trip serialization through the cast.
     */
    public function testRoundTripSerializationThroughCast()
    {
        $originalSettings = new QuickbooksSettings([
            'accessTokenKey' => 'test_token_123',
            'refresh_token' => 'refresh_token_456',
            'realmID' => 'realm_789',
            'accessTokenExpiresAt' => 1234567890,
            'refreshTokenExpiresAt' => 9876543210,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::PULL->value,
                ],
                'invoice' => [
                    'direction' => SyncDirection::PUSH->value,
                ],
                'product' => [
                    'direction' => SyncDirection::BIDIRECTIONAL->value,
                ],
            ],
        ]);

        $cast = new QuickbooksSettingsCast();

        // Create a mock model for the cast
        $model = new class extends Model {
            // Empty model for testing
        };

        // Serialize (set)
        $serialized = $cast->set($model, 'quickbooks', $originalSettings, []);
        
        $this->assertNotNull($serialized);
        $this->assertIsString($serialized);

        // Deserialize (get)
        $deserialized = $cast->get($model, 'quickbooks', $serialized, []);

        $this->assertInstanceOf(QuickbooksSettings::class, $deserialized);
        
        // Verify all properties are preserved
        $this->assertEquals($originalSettings->accessTokenKey, $deserialized->accessTokenKey);
        $this->assertEquals($originalSettings->refresh_token, $deserialized->refresh_token);
        $this->assertEquals($originalSettings->realmID, $deserialized->realmID);
        $this->assertEquals($originalSettings->accessTokenExpiresAt, $deserialized->accessTokenExpiresAt);
        $this->assertEquals($originalSettings->refreshTokenExpiresAt, $deserialized->refreshTokenExpiresAt);
        $this->assertEquals($originalSettings->baseURL, $deserialized->baseURL);

        // Verify nested settings are preserved
        $this->assertInstanceOf(QuickbooksSync::class, $deserialized->settings);

        // Verify enum values are preserved correctly
        $this->assertInstanceOf(QuickbooksSyncMap::class, $deserialized->settings->client);
        $this->assertEquals(SyncDirection::PULL, $deserialized->settings->client->direction);
        
        $this->assertInstanceOf(QuickbooksSyncMap::class, $deserialized->settings->invoice);
        $this->assertEquals(SyncDirection::PUSH, $deserialized->settings->invoice->direction);
        
        $this->assertInstanceOf(QuickbooksSyncMap::class, $deserialized->settings->product);
        $this->assertEquals(SyncDirection::BIDIRECTIONAL, $deserialized->settings->product->direction);
    }

    /**
     * Test that all entity types are properly serialized.
     */
    public function testAllEntityTypesAreSerialized()
    {
        $settings = new QuickbooksSettings([
            'settings' => [
                'client' => ['direction' => SyncDirection::PULL->value],
                'vendor' => ['direction' => SyncDirection::PUSH->value],
                'invoice' => ['direction' => SyncDirection::BIDIRECTIONAL->value],
                'sales' => ['direction' => SyncDirection::PULL->value],
                'quote' => ['direction' => SyncDirection::PUSH->value],
                'purchase_order' => ['direction' => SyncDirection::BIDIRECTIONAL->value],
                'product' => ['direction' => SyncDirection::PULL->value],
                'payment' => ['direction' => SyncDirection::PUSH->value],
                'expense' => ['direction' => SyncDirection::BIDIRECTIONAL->value],
            ],
        ]);

        $array = $settings->toArray();

        $entities = ['client', 'vendor', 'invoice', 'sales', 'quote', 'purchase_order', 'product', 'payment', 'expense'];
        
        foreach ($entities as $entity) {
            $this->assertArrayHasKey($entity, $array['settings'], "Entity {$entity} should be in serialized array");
            $this->assertArrayHasKey('direction', $array['settings'][$entity], "Entity {$entity} should have direction");
            $this->assertIsString($array['settings'][$entity]['direction'], "Entity {$entity} direction should be a string");
        }
    }

    /**
     * Test that empty/default settings serialize correctly.
     */
    public function testEmptySettingsSerializeCorrectly()
    {
        $settings = new QuickbooksSettings();

        $array = $settings->toArray();

        // Verify all OAuth fields have default values
        $this->assertEquals('', $array['accessTokenKey']);
        $this->assertEquals('', $array['refresh_token']);
        $this->assertEquals('', $array['realmID']);
        $this->assertEquals(0, $array['accessTokenExpiresAt']);
        $this->assertEquals(0, $array['refreshTokenExpiresAt']);
        $this->assertEquals('', $array['baseURL']);

        // Verify settings structure exists
        $this->assertIsArray($array['settings']);
        $this->assertArrayHasKey('client', $array['settings']);
        
        // Verify default direction is BIDIRECTIONAL
        $this->assertEquals('none', $array['settings']['client']['direction']);
    }

    /**
     * Test that JSON produced by toArray() can be decoded and reconstructed.
     */
    public function testJsonCanBeDecodedAndReconstructed()
    {
        $originalSettings = new QuickbooksSettings([
            'accessTokenKey' => 'token_123',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::PULL->value,
                ],
            ],
        ]);

        // Serialize to JSON
        $json = json_encode($originalSettings->toArray());
        $this->assertIsString($json);

        // Decode JSON
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        // Reconstruct from decoded array
        $reconstructed = QuickbooksSettings::fromArray($decoded);

        // Verify reconstruction
        $this->assertEquals($originalSettings->accessTokenKey, $reconstructed->accessTokenKey);
        $this->assertEquals($originalSettings->settings->client->direction, $reconstructed->settings->client->direction);
    }
}
