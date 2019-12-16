<?php

namespace App\Jobs\Util;

use App\Models\User;
use App\Models\Users\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class StoreFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Instance of file.
     */
    private $file;

    /**
     * Type of file for upload. (Models\Users\Upload)
     */
    private $type;

    /**
     * Disk to store.
     *
     * @var string
     */
    private $disk;

    /**
     * @var null|User
     */
    private $user;

    /**
     * Create a new job instance.
     *
     * @param $file
     * @param $type
     * @param string $disk
     * @param null $user
     */
    public function __construct($file, $type, $disk = 'local', $user = null)
    {
        $this->file = $file;
        $this->type = $type;
        $this->disk = $disk;
        $this->user = $user ?? auth()->user();
    }

    /**
     * Execute the job.
     *
     * @return Upload|null
     */
    public function handle(): ?Upload
    {
        $path = Storage::disk($this->disk)->put(
            $this->type, $this->file
        );

        return Upload::create([
            'type' => $this->type,
            'user_id' => $this->user->id,
            'title' => $this->file->getClientOriginalName(),
            'size' => $this->file->getClientSize(),
            'path' => $path,
            'extension' => $this->file->extension(),
        ]);
    }
}
