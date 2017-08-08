<?php

namespace App\Jobs;

use App\Ninja\Mailers\ContactMailer;
use Illuminate\Bus\Queueable;
use Monolog\Logger;

abstract class Job
{
    use Queueable;

    /**
     * The name of the job.
     *
     * @var string
     */
    protected $jobName;

    /*
     * Handle a job failure.
     *
     * @param ContactMailer $mailer
     * @param Logger $logger
     */
     /*
    protected function failed(ContactMailer $mailer, Logger $logger)
    {
        if(config('queue.failed.notify_email')) {
            $mailer->sendTo(
                config('queue.failed.notify_email'),
                config('mail.from.address'),
                config('mail.from.name'),
                config('queue.failed.notify_subject', trans('texts.job_failed', ['name'=>$this->jobName])),
                'job_failed',
                [
                    'name' => $this->jobName,
                ]
            );
        }

        $logger->error(
            trans('texts.job_failed', ['name' => $this->jobName])
        );
    }
    */
}
