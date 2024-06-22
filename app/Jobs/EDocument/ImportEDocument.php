<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\EDocument;

use App\Models\Expense;
use App\Services\EDocument\Imports\ParseEDocument;
use App\Utils\TempFile;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private readonly string $file_content, private string $file_name, private string $file_mime_type)
    {

    }

    /**
     * Execute the job.
     *
     * @return Expense
     * @throws \Exception
     */
    public function handle(): Expense
    {

        $file = TempFile::UploadedFileFromRaw($this->file_content, $this->file_name, $this->file_mime_type);

        return (new ParseEDocument($file))->run();

    }
}
