<?php


namespace App\Jobs\Report;


use App\Http\Requests\Report\GenericReportRequest;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\DownloadReport;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendToAdmin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Company $company;
    protected array $request;
    protected string $report_class;
    protected string $file_name;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(Company $company, array $request, $report_class, $file_name)
    {
        $this->company = $company;
        $this->request = $request;
        $this->report_class = $report_class;
        $this->file_name = $file_name;

    }

    public function handle()
    {
        $export = new $this->report_class($this->company, $this->request);
        $csv = $export->run();

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new DownloadReport($this->company, $csv, $this->file_name);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $this->company->owner();

        NinjaMailerJob::dispatch($nmo);

    }
}
