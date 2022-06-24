<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Jobs\Util\UploadFile;
use App\Models\Account;
use App\Models\Company;

trait SavesDocuments
{
    public function saveDocuments($document_array, $entity, $is_public = true)
    {
        if ($entity instanceof Company) {
            $account = $entity->account;
            $company = $entity;
            $user = auth()->user();
        } else {
            $account = $entity->company->account;
            $company = $entity->company;
            $user = $entity->user;
        }

        if (! $account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            return false;
        }

        foreach ($document_array as $document) {
            $document = (new UploadFile(
                $document,
                UploadFile::DOCUMENT,
                $user,
                $company,
                $entity,
                null,
                $is_public
            ))->handle();
        }
    }

    public function saveDocument($document, $entity, $is_public = true)
    {
        if ($entity instanceof Company) {
            $account = $entity->account;
            $company = $entity;
            $user = auth()->user();
        } else {
            $account = $entity->company->account;
            $company = $entity->company;
            $user = $entity->user;
        }

        if (! $account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            return false;
        }

        $document = (new UploadFile(
            $document,
            UploadFile::DOCUMENT,
            $user,
            $company,
            $entity,
            null,
            $is_public
        ))->handle();
    }
}
