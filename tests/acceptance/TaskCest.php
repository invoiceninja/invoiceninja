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
        $description = $this->faker->text(100);

        $I->wantTo('create a timed task');
        $I->amOnPage('/tasks/create');
        $I->seeCurrentUrlEquals('/tasks/create');

        $I->fillField('#description', $description);

        $I->click('Start');
        $I->wait(rand(2, 5));
        $I->click('Stop');
        $I->click('Save');

        $I->seeInDatabase('tasks', ['description' => $description]);
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
