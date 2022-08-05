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

namespace App\Http\Controllers;

use App\Http\Requests\Activity\DownloadHistoricalEntityRequest;
use App\Models\Activity;
use App\Transformers\ActivityTransformer;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankIntegrationController extends BaseController
{

    protected $entity_type = BankIntegration::class;

    protected $entity_transformer = BankIntegrationTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }


}