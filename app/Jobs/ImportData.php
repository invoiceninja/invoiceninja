<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Monolog\Logger;
use App\Services\ImportService;
use App\Ninja\Mailers\UserMailer;
use App\Models\User;
use Auth;
use App;

/**
 * Class SendInvoiceEmail.
 */
class ImportData extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var string
     */
    protected $server;

    /**
     * Create a new job instance.
     *
     * @param mixed   $files
     * @param mixed   $settings
     */
    public function __construct(User $user, $type, $settings)
    {
        $this->user = $user;
        $this->type = $type;
        $this->settings = $settings;
        $this->server = config('database.default');
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(ImportService $importService, UserMailer $userMailer)
    {
        $includeSettings = false;

        if (App::runningInConsole()) {
            Auth::onceUsingId($this->user->id);
            $this->user->account->loadLocalizationSettings();
        }

        if ($this->type === IMPORT_JSON) {
            $includeData = $this->settings['include_data'];
            $includeSettings = $this->settings['include_settings'];
            $files = $this->settings['files'];
            $results = $importService->importJSON($files[IMPORT_JSON], $includeData, $includeSettings);
        } elseif ($this->type === IMPORT_CSV) {
            $map = $this->settings['map'];
            $headers = $this->settings['headers'];
            $timestamp = $this->settings['timestamp'];
            $results = $importService->importCSV($map, $headers, $timestamp);
        } else {
            $source = $this->settings['source'];
            $files = $this->settings['files'];
            $results = $importService->importFiles($source, $files);
        }

        $subject = trans('texts.import_complete');
        $message = $importService->presentResults($results, $includeSettings);
        $userMailer->sendMessage($this->user, $subject, $message);

        if (App::runningInConsole()) {
            Auth::logout();
        }
    }
}
