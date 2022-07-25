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

namespace App\Models;

use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class Backup extends BaseModel
{
    public function getEntityType()
    {
        return self::class;
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function storeRemotely(?string $html, Client $client)
    {
        if (! $html || strlen($html) == 0) {
            return;
        }

        $path = $client->backup_path().'/';
        $filename = now()->format('Y_m_d').'_'.md5(time()).'.html';
        $file_path = $path.$filename;

        Storage::disk(config('filesystems.default'))->makeDirectory($path, 0775);

        Storage::disk(config('filesystems.default'))->put($file_path, $html);

        if (Storage::disk(config('filesystems.default'))->exists($file_path)) {
            $this->html_backup = '';
            $this->filename = $file_path;
            $this->save();
        }
    }

    public function deleteFile()
    {
        nlog('deleting => '.$this->filename);

        try {
            Storage::disk(config('filesystems.default'))->delete($this->filename);
        } catch (\Exception $e) {
            nlog('BACKUPEXCEPTION deleting backup file with error '.$e->getMessage());
        }
    }
}
