<?php

use Faker\Factory;

class TaskCest
{
    /**
     * @var \Faker\Generator
     */
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function createTimerTask(AcceptanceTester $I)
    {
        $clientName = $this->faker->name;
        $clientEmail = $this->faker->safeEmail;
        $project = $this->faker->text(20);
        $description = $this->faker->text(100);

        $I->wantTo('create a timed task');

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'name'], $clientName);
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);
        $clientId = $I->grabFromDatabase('clients', 'id', ['name' => $clientName]);

        $I->amOnPage('/tasks/create');
        $I->seeCurrentUrlEquals('/tasks/create');

        $I->selectDropdown($I, $clientName, '.client-select .dropdown-toggle');
        $I->selectDropdownCreate($I, 'project', $project);
        $I->fillField('#description', $description);

        $I->click('Start');
        $I->wait(rand(2, 5));
        $I->click('Stop');
        $I->click('Save');

        $I->seeInDatabase('tasks', [
            'description' => $description,
            'client_id' => $clientId,
        ]);
        $I->seeInDatabase('projects', ['name' => $project]);

        $I->click('More Actions');
        $I->click('Invoice Task');
        $I->click('Mark Sent');
        $I->see('Sent');
        $I->see('Successfully created invoice');
    }

    public function createManualTask(AcceptanceTester $I)
    {
        $description = $this->faker->text(100);

        $I->wantTo('create a manual task');
        $I->amOnPage('/tasks/create');
        $I->seeCurrentUrlEquals('/tasks/create');

        $I->selectOption('#task_type3', 'Manual');
        $I->fillField('#description', $description);

        $I->click('Save');

        $I->seeInDatabase('tasks', ['description' => $description]);
    }


    public function editTask(AcceptanceTester $I)
    {
        $description = $this->faker->text(100);

        $I->wantTo('edit a task');
        $I->amOnPage('/tasks/1/edit');
        $I->seeCurrentUrlEquals('/tasks/1/edit');

        $I->fillField('#description', $description);

        $I->click('Save');

        $I->seeInDatabase('tasks', ['description' => $description]);
    }

    public function listTasks(AcceptanceTester $I)
    {
        $I->wantTo('list tasks');
        $I->amOnPage('/tasks');

        $I->seeNumberOfElements('tbody tr[role=row]', [1, 10]);
    }

    /*
    public function deleteTask(AcceptanceTester $I)
    {
        $I->wantTo('delete a Task');
        $I->amOnPage('/tasks');

        $task_id = Helper::getRandom('Task', 'public_id');

        //delete task
        $I->executeJS(sprintf('deleteEntity(%d)', $task_id));
        $I->acceptPopup();

        //check if Task was delete
        $I->wait(2);
        $I->seeInDatabase('tasks', ['public_id' => $task_id, 'is_deleted' => true]);
    }
    */
}
