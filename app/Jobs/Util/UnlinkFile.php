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

namespace App\Jobs\Util;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UnlinkFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected string $disk = '', protected ?string $file_path = '')
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /* Do not delete files if we are on the sync queue*/
        if (config('queue.default') == 'sync') {
            return;
        }


        if (!$this->file_path) {
            return;
        }

        try {
            Storage::disk($this->disk)->delete($this->file_path);
        } catch (\Exception $e) {

        }
    }
}
