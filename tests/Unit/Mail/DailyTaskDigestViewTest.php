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

namespace Tests\Unit\Mail;

use Tests\TestCase;

class DailyTaskDigestViewTest extends TestCase
{
    public function test_html_digest_renders_summary_sections_and_task_details(): void
    {
        $html = view('email.admin.daily_task_digest', $this->viewData())->render();

        $this->assertStringContainsString('<!DOCTYPE html', $html);
        $this->assertStringContainsString('Your daily task digest', $html);
        $this->assertStringContainsString('Overdue', $html);
        $this->assertStringContainsString('Prepare quarterly review', $html);
        $this->assertStringContainsString('ACME Corp · Website refresh', $html);
        $this->assertStringContainsString('Due yesterday · In progress · 2h estimated', $html);
        $this->assertStringContainsString('https://example.test/tasks/task-1001', $html);
        $this->assertStringContainsString('View all tasks', $html);
        $this->assertStringNotContainsString('Empty section', $html);
    }

    public function test_text_digest_renders_readable_task_groups_and_links(): void
    {
        $text = view('email.admin.daily_task_digest_text', $this->viewData())->render();

        $this->assertStringContainsString('Your daily task digest', $text);
        $this->assertStringContainsString('Overdue: 1', $text);
        $this->assertStringContainsString('Overdue (1)', $text);
        $this->assertStringContainsString('- Prepare quarterly review', $text);
        $this->assertStringContainsString('Due yesterday | In progress | 2h estimated', $text);
        $this->assertStringContainsString('View all tasks: https://example.test/tasks', $text);
        $this->assertStringNotContainsString('<table', $text);
        $this->assertStringNotContainsString('Empty section', $text);
    }

    private function viewData(): array
    {
        return [
            'preheader' => 'One overdue task needs your attention.',
            'greeting' => 'Good morning, Taylor.',
            'title' => 'Your daily task digest',
            'report_date' => 'Monday, 31 August 2026',
            'intro' => 'Here is what needs your attention today.',
            'summary' => [
                ['label' => 'Overdue', 'value' => 1, 'tone' => 'critical'],
                ['label' => 'Due today', 'value' => 2, 'tone' => 'warning'],
                ['label' => 'Upcoming', 'value' => 4, 'tone' => 'info'],
            ],
            'sections' => [
                [
                    'title' => 'Overdue',
                    'count' => 1,
                    'tone' => 'critical',
                    'tasks' => [
                        [
                            'title' => 'Prepare quarterly review',
                            'context' => 'ACME Corp · Website refresh',
                            'metadata' => ['Due yesterday', 'In progress', '2h estimated'],
                            'url' => 'https://example.test/tasks/task-1001',
                        ],
                    ],
                ],
                [
                    'title' => 'Empty section',
                    'count' => 0,
                    'tone' => 'neutral',
                    'tasks' => [],
                ],
            ],
            'url' => 'https://example.test/tasks',
            'button' => 'View all tasks',
            'signature' => 'Thanks,<br>Invoice Ninja',
            'logo' => '',
            'settings' => (object) [
                'primary_color' => '#4caf50',
                'email_alignment' => 'left',
                'email_style' => 'light',
            ],
            'whitelabel' => true,
        ];
    }
}
