<?php

namespace Tests\Unit\FranceEReporting;

use App\Models\TransactionEvent;
use Tests\TestCase;

class SubmitFranceEReportTest extends TestCase
{
    public function testSubmitJobNoLongerUsesACompiledSubmissionState(): void
    {
        $source = file_get_contents(app_path("Jobs/EDocument/SubmitFranceEReport.php"));

        $this->assertFalse(defined(TransactionEvent::class."::FR_REPORTING_STATUS_COMPILED"));
        $this->assertStringNotContainsString("createSubmissionEvent", $source);
        $this->assertStringNotContainsString("FR_REPORTING_STATUS_COMPILED", $source);
        $this->assertStringContainsString("recordSubmissionAttempt", $source);
    }
}
