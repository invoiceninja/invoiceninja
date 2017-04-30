<?php

namespace App\Console\Commands;

use Utils;
use stdClass;
use App\Models\Account;
use Faker\Factory;
use Illuminate\Console\Command;

/**
 * Class CreateLuisData.
 */
class CreateLuisData extends Command
{
    /**
     * @var string
     */
    protected $description = 'Create LUIS Data';

    /**
     * @var string
     */
    protected $signature = 'ninja:create-luis-data {faker_field=name}';

    /**
     * CreateLuisData constructor.
     *
     */
    public function __construct()
    {
        parent::__construct();

        $this->faker = Factory::create();
    }

    /**
     * @return bool
     */
    public function fire()
    {
        $this->fakerField = $this->argument('faker_field');

        $intents = [];
        $entityTypes = [
            ENTITY_INVOICE,
            ENTITY_QUOTE,
            ENTITY_CLIENT,
            ENTITY_CREDIT,
            ENTITY_EXPENSE,
            ENTITY_PAYMENT,
            ENTITY_PRODUCT,
            ENTITY_RECURRING_INVOICE,
            ENTITY_TASK,
            ENTITY_VENDOR,
        ];

        foreach ($entityTypes as $entityType) {
            $intents = array_merge($intents, $this->createIntents($entityType));
        }

        $intents = array_merge($intents, $this->getNavigateToIntents($entityType));

        $this->info(json_encode($intents));
    }

    private function createIntents($entityType)
    {
        $intents = [];

        $intents = array_merge($intents, $this->getCreateEntityIntents($entityType));
        $intents = array_merge($intents, $this->getFindEntityIntents($entityType));
        $intents = array_merge($intents, $this->getListEntityIntents($entityType));

        return $intents;
    }

    private function getCreateEntityIntents($entityType)
    {
        $intents = [];
        $phrases = [
            "create new {$entityType}",
            "new {$entityType}",
            "make a {$entityType}",
        ];

        foreach ($phrases as $phrase) {
            $intents[] = $this->createIntent('CreateEntity', $phrase, [
                $entityType => 'EntityType',
            ]);
            if ($entityType != ENTITY_CLIENT) {
                $client = $this->faker->{$this->fakerField};
                $phrase .= " for {$client}";
                $intents[] = $this->createIntent('CreateEntity', $phrase, [
                    $entityType => 'EntityType',
                    $client => 'Name',
                ]);
            }
        }

        return $intents;
    }

    private function getFindEntityIntents($entityType)
    {
        $intents = [];

        if (in_array($entityType, [ENTITY_CLIENT, ENTITY_INVOICE, ENTITY_QUOTE])) {
            $name = $entityType === ENTITY_CLIENT ? $this->faker->{$this->fakerField} : $this->faker->randomNumber(4);
            $intents[] = $this->createIntent('FindEntity', "find {$entityType} {$name}", [
                $entityType => 'EntityType',
                $name => 'Name',
            ]);
            if ($entityType === ENTITY_CLIENT) {
                $name = $this->faker->{$this->fakerField};
                $intents[] = $this->createIntent('FindEntity', "find {$name}", [
                    $name => 'Name',
                ]);
            }
        }

        return $intents;
    }

    private function getListEntityIntents($entityType)
    {
        $intents = [];
        $entityTypePlural = Utils::pluralizeEntityType($entityType);

        $intents[] = $this->createIntent('ListEntity', "show me {$entityTypePlural}", [
            $entityTypePlural => 'EntityType',
        ]);
        $intents[] = $this->createIntent('ListEntity', "list {$entityTypePlural}", [
            $entityTypePlural => 'EntityType',
        ]);

        $intents[] = $this->createIntent('ListEntity', "show me active {$entityTypePlural}", [
            $entityTypePlural => 'EntityType',
            'active' => 'Filter',
        ]);
        $intents[] = $this->createIntent('ListEntity', "list archived and deleted {$entityTypePlural}", [
            $entityTypePlural => 'EntityType',
            'archived' => 'Filter',
            'deleted' => 'Filter',
        ]);

        if ($entityType != ENTITY_CLIENT) {
            $client = $this->faker->{$this->fakerField};
            $intents[] = $this->createIntent('ListEntity', "list {$entityTypePlural} for {$client}", [
                $entityTypePlural => 'EntityType',
                $client => 'Name',
            ]);
            $intents[] = $this->createIntent('ListEntity', "show me {$client}'s {$entityTypePlural}", [
                $entityTypePlural => 'EntityType',
                $client . '\'s' => 'Name',
            ]);
            $intents[] = $this->createIntent('ListEntity', "show me {$client}'s active {$entityTypePlural}", [
                $entityTypePlural => 'EntityType',
                $client . '\'s'  => 'Name',
                'active' => 'Filter',
            ]);
        }

        return $intents;
    }

    private function getNavigateToIntents($entityType)
    {
        $intents = [];
        $locations = array_merge(Account::$basicSettings, Account::$advancedSettings);

        foreach ($locations as $location) {
            $location = str_replace('_', ' ', $location);
            $intents[] = $this->createIntent('NavigateTo', "go to {$location}", [
                $location => 'Location',
            ]);
            $intents[] = $this->createIntent('NavigateTo', "show me {$location}", [
                $location => 'Location',
            ]);
        }

        return $intents;
    }

    private function createIntent($name, $text, $entities)
    {
        $intent = new stdClass();
        $intent->intent = $name;
        $intent->text = $text;
        $intent->entities = [];

        foreach ($entities as $value => $entity) {
            $startPos = strpos($text, (string)$value);
            if (! $startPos) {
                dd("Failed to find {$value} in {$text}");
            }
            $entityClass = new stdClass();
            $entityClass->entity = $entity;
            $entityClass->startPos = $startPos;
            $entityClass->endPos = $entityClass->startPos + strlen($value) - 1;
            $intent->entities[] = $entityClass;
        }

        return $intent;
    }


    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
