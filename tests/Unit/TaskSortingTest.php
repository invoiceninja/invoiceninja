<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Tests\TestCase;

/**
 * 
 */
class TaskSortingTest extends TestCase
{
    public $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = collect([
            ['id' => 1, 'name' => 'pizza', 'order' => 9999],
            ['id' => 2, 'name' => 'pineapple', 'order' => 9999],
            ['id' => 3, 'name' => 'ethereum', 'order' => 9999],
            ['id' => 4, 'name' => 'bitcoin', 'order' => 9999],
            ['id' => 5, 'name' => 'zulu', 'order' => 9999],
            ['id' => 6, 'name' => 'alpha', 'order' => 9999],
            ['id' => 7, 'name' => 'ninja', 'order' => 9999],
        ]);
    }

    public function testSorting()
    {
        $index = 3;
        $item = $this->collection->where('id', 7)->first();

        $new_collection = $this->collection->reject(function ($task) use ($item) {
            return $item['id'] == $task['id'];
        });

        $sorted_tasks = $new_collection->filter(function ($task, $key) use ($index) {
            return $key < $index;
        })->push($item)->merge($new_collection->filter(function ($task, $key) use ($index) {
            return $key >= $index;
        }))->map(function ($item, $key) {
            $item['order'] = $key;

            return $item;
        });

        $index_item = $sorted_tasks->splice($index, 1)->all();

        $this->assertEquals($sorted_tasks->first()['name'], 'pizza');
        $this->assertEquals($sorted_tasks->last()['name'], 'alpha');
        $this->assertEquals($index_item[0]['name'], 'ninja');
    }
}
