<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail\Webhook;

use App\Factory\ExpenseFactory;
use App\Models\Company;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\SavesDocuments;

interface BaseWebhookHandler
{
    use GeneratesCounter;
    use SavesDocuments;
    public function process()
    {

    }
    protected function createExpense(string $email, string $subject, string $plain_message, string $html_message, string $date, array $documents)
    {
        $company = $this->matchCompany($email);
        if (!$company)
            return false;

        $expense = ExpenseFactory::create($company->id, $company->owner()->id);

        $expense->public_notes = $subject;
        $expense->private_notes = $plain_message;
        $expense->date = $date;

        // TODO: add html_message as document to the expense

        $this->saveDocuments($documents, $expense);

        $expense->saveQuietly();

        return $expense;
    }

    private function matchCompany(string $email)
    {
        return Company::where("expense_mailbox", $email)->first();
    }
}
