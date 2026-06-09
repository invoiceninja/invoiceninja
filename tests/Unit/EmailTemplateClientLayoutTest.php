<?php

namespace Tests\Unit;

use Tests\TestCase;

class EmailTemplateClientLayoutTest extends TestCase
{
    public function testClientEmailSignatureBandStylesBackgroundOnTableCell(): void
    {
        $html = view('email.template.client', [
            'settings' => (object) [
                'primary_color' => '#4caf50',
                'email_alignment' => 'center',
                'email_style' => 'dark',
            ],
            'logo' => '',
            'body' => '<p>Hello</p>',
            'signature' => 'Thanks',
            'links' => [],
            'email_preferences' => false,
        ])->render();

        $this->assertStringContainsString(
            '<td class="dark-bg dark-text-white" bgcolor="#f9f9f9" height="40" valign="middle" align="center"',
            $html
        );
        $this->assertStringContainsString('background-color: #f9f9f9;', $html);
        $this->assertStringNotContainsString('<div class="dark-bg dark-text-white">', $html);
    }
}
