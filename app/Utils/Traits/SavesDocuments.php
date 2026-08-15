<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Jobs\Util\UploadFile;
use App\Models\Account;
use App\Models\Company;

trait SavesDocuments
{
    public function saveDocuments($document_array, $entity, ?bool $is_public = null)
    {
        if ($entity instanceof Company) {
            $account = $entity->account;
            $company = $entity;
            $user = auth()->user();
        } else {
            $account = $entity->company->account;
            $company = $entity->company;
            $user = $entity->user ?? auth()->user();
        }

        if (! $account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            return false;
        }

        if (!is_array($document_array)) {
            return;
        }

        $is_public ??= (bool) $company->getSetting('documents_public_by_default');

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

        $entity->touch();
    }

    public function saveDocument($document, $entity, $force_save = false, ?bool $is_public = null)
    {
        if ($entity instanceof Company) {
            $account = $entity->account;
            $company = $entity;
            $user = auth()->user();
        } else {
            $account = $entity->company->account;
            $company = $entity->company;
            $user = $entity->user ?? auth()->user();
        }

        if (! $force_save && ! $account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            return false;
        }

        $is_public ??= (bool) $company->getSetting('documents_public_by_default');

        $document = (new UploadFile(
            $document,
            UploadFile::DOCUMENT,
            $user,
            $company,
            $entity,
            null,
            $is_public
        ))->handle();

        $entity->touch();

    }
}
