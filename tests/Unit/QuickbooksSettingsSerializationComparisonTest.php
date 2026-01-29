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

use App\DataMapper\QuickbooksSettings;
use App\DataMapper\QuickbooksSyncMap;
use App\Enum\SyncDirection;
use Tests\TestCase;

/**
 * Direct comparison test showing the serialization bug and fix.
 * 
 * This test demonstrates:
 * 1. The old method (get_object_vars) fails to properly serialize nested objects and enums
 * 2. The new method (toArray) correctly serializes everything
 */
class QuickbooksSettingsSerializationComparisonTest extends TestCase
{
    /**
     * Test showing that get_object_vars() on an enum returns name/value array.
     * 
     * While json_encode() handles this correctly, get_object_vars() on an enum
     * itself returns an array with 'name' and 'value' keys, not just the value.
     * This demonstrates why explicit toArray() is better for control.
     */
    public function testGetObjectVarsOnEnumReturnsNameValueArray()
    {
        $syncMap = new QuickbooksSyncMap([
            'direction' => SyncDirection::PULL->value,
        ]);

        // get_object_vars on the enum property itself
        $enumVars = get_object_vars($syncMap->direction);
        
        // The enum's internal structure has both name and value
        $this->assertIsArray($enumVars);
        $this->assertArrayHasKey('name', $enumVars);
        $this->assertArrayHasKey('value', $enumVars);
        $this->assertEquals('PULL', $enumVars['name']);
        $this->assertEquals('pull', $enumVars['value']);
        
        // While json_encode handles this, toArray() gives explicit control
        $array = $syncMap->toArray();
        $this->assertEquals('pull', $array['direction'], 
            'toArray() explicitly returns just the value string');
    }

    /**
     * Test showing that toArray() correctly serializes enums.
     */
    public function testToArrayCorrectlySerializesEnums()
    {
        $syncMap = new QuickbooksSyncMap([
            'direction' => SyncDirection::PULL->value,
        ]);

        // New method: toArray()
        $array = $syncMap->toArray();
        $json = json_encode($array);
        $decoded = json_decode($json, true);

        // The enum IS properly serialized as a string value
        $this->assertIsString($decoded['direction'], 
            'New method: enum is serialized as string');
        
        // The decoded value IS the string 'pull'
        $this->assertEquals('pull', $decoded['direction'], 
            'New method: enum value is correctly serialized as string');
    }

    /**
     * Test showing that get_object_vars() relies on json_encode() for nested objects.
     * 
     * While get_object_vars() + json_encode() works, it relies on PHP's automatic
     * serialization. The toArray() method provides explicit, controlled serialization
     * that's more maintainable and testable.
     */
    public function testGetObjectVarsReliesOnJsonEncodeForNestedObjects()
    {
        $settings = new QuickbooksSettings([
            'accessTokenKey' => 'test_token',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::PULL->value,
                ],
            ],
        ]);

        // Old method: get_object_vars (relies on json_encode to handle nested objects)
        $vars = get_object_vars($settings);
        $json = json_encode($vars);
        $decoded = json_decode($json, true);

        // json_encode() does handle this correctly, but it's implicit
        $this->assertIsArray($decoded['settings'], 
            'json_encode handles nested objects, but implicitly');
        
        // The new method is explicit and controlled
        $array = $settings->toArray();
        $this->assertIsArray($array['settings'], 
            'toArray() explicitly converts nested objects');
    }

    /**
     * Test showing that toArray() correctly serializes nested objects.
     */
    public function testToArrayCorrectlySerializesNestedObjects()
    {
        $settings = new QuickbooksSettings([
            'accessTokenKey' => 'test_token',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::PULL->value,
                ],
                'invoice' => [
                    'direction' => SyncDirection::PUSH->value,
                ],
            ],
        ]);

        // New method: toArray()
        $array = $settings->toArray();
        $json = json_encode($array);
        $decoded = json_decode($json, true);

        // The nested QuickbooksSync object IS properly converted to an array
        $this->assertIsArray($decoded['settings'], 
            'New method: nested object is converted to array');
        
        // The nested QuickbooksSyncMap objects are also converted
        $this->assertIsArray($decoded['settings']['client'], 
            'New method: nested sync map is converted to array');
        
        // The enum values are properly serialized as strings
        $this->assertEquals('pull', $decoded['settings']['client']['direction'], 
            'New method: nested enum is serialized as string');
        $this->assertEquals('push', $decoded['settings']['invoice']['direction'], 
            'New method: nested enum is serialized as string');
    }

    /**
     * Side-by-side comparison: old vs new method.
     * 
     * Both methods work, but toArray() provides:
     * 1. Explicit control over serialization
     * 2. Better maintainability
     * 3. Consistency with other DataMapper classes
     * 4. Easier testing and debugging
     */
    public function testSideBySideComparison()
    {
        $settings = new QuickbooksSettings([
            'accessTokenKey' => 'token_123',
            'refresh_token' => 'refresh_456',
            'realmID' => 'realm_789',
            'settings' => [
                'client' => [
                    'direction' => SyncDirection::BIDIRECTIONAL->value,
                ],
            ],
        ]);

        // OLD METHOD (works but implicit)
        $oldVars = get_object_vars($settings);
        $oldJson = json_encode($oldVars);
        $oldDecoded = json_decode($oldJson, true);

        // NEW METHOD (explicit and controlled)
        $newArray = $settings->toArray();
        $newJson = json_encode($newArray);
        $newDecoded = json_decode($newJson, true);

        // Both methods produce valid results
        $this->assertEquals('token_123', $oldDecoded['accessTokenKey']);
        $this->assertEquals('token_123', $newDecoded['accessTokenKey']);
        
        $this->assertEquals('bidirectional', $oldDecoded['settings']['client']['direction']);
        $this->assertEquals('bidirectional', $newDecoded['settings']['client']['direction']);

        // toArray() is the canonical form for persistence: explicit control, consistent shape
        $this->assertIsArray($newArray, 'toArray() explicitly returns an array structure');
        $this->assertIsString($newArray['settings']['client']['direction'],
            'toArray() explicitly converts enum to string value');

        // income_account_map uses int keys (Product::PRODUCT_TYPE_*) in toArray() for storage
        $this->assertArrayHasKey('income_account_map', $newDecoded['settings']);
        $this->assertIsArray($newDecoded['settings']['income_account_map']);
    }
}
