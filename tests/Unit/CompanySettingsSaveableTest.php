<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\DataMapper\CompanySettings;
use Tests\TestCase;

/**
 *
 *   App\Http\Requests\Company\UpdateCompanyRequest
 */
class CompanySettingsSaveableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSettingsSaverWithFreePlan()
    {
        $filtered = $this->filterSaver(CompanySettings::defaults());

        $this->assertTrue(property_exists($filtered, 'timezone_id'));

        $this->assertTrue(property_exists(CompanySettings::defaults(), 'timezone_id'));

        $this->assertTrue(property_exists(CompanySettings::defaults(), 'auto_archive_invoice'));

        $this->assertTrue(property_exists($filtered, 'auto_archive_invoice'));
    }

    public function testFreePlanCastsExcludeEveryEmailTemplateProperty(): void
    {
        $email_template_keys = $this->emailTemplateSettingKeys();

        $this->assertNotEmpty($email_template_keys);
        $this->assertContains('email_template_invoice', $email_template_keys);
        $this->assertContains('email_subject_invoice', $email_template_keys);
        $this->assertContains('email_style_custom', $email_template_keys);

        foreach ($email_template_keys as $key) {
            $this->assertArrayNotHasKey(
                $key,
                CompanySettings::$free_plan_casts,
                "Free hosted plans must not be able to persist {$key}"
            );
        }
    }

    public function testFreePlanFilterStripsEveryEmailTemplateProperty(): void
    {
        $settings = CompanySettings::defaults();

        foreach ($this->emailTemplateSettingKeys() as $key) {
            $settings->{$key} = '<p>INJECTED '.$key.'</p>';
        }

        $filtered = $this->filterSaver($settings);

        foreach ($this->emailTemplateSettingKeys() as $key) {
            $this->assertFalse(
                property_exists($filtered, $key),
                "Free plan filter left {$key} on the settings object"
            );
        }
    }

    public function testFreePlanKeepsHiddenPaymentTypes(): void
    {
        $settings = CompanySettings::defaults();
        $settings->hidden_payment_types = '3,25';

        $filtered = $this->filterSaver($settings);

        $this->assertSame('3,25', $filtered->hidden_payment_types);
    }

    public function testFreePlanKeepsFileNamePatterns(): void
    {
        $settings = CompanySettings::defaults();
        $settings->invoice_filename_pattern = '$number';
        $settings->quote_filename_pattern = '$number';
        $settings->credit_filename_pattern = '$number';
        $settings->purchase_order_filename_pattern = '$number';
        $settings->filename_slug = true;

        $filtered = $this->filterSaver($settings);

        $this->assertSame('$number', $filtered->invoice_filename_pattern);
        $this->assertSame('$number', $filtered->quote_filename_pattern);
        $this->assertSame('$number', $filtered->credit_filename_pattern);
        $this->assertSame('$number', $filtered->purchase_order_filename_pattern);
        $this->assertTrue($filtered->filename_slug);
    }

    private function filterSaver($settings)
    {
        $saveable_cast = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_cast)) {
                unset($settings->{$key});
            }
        }

        return $settings;
    }

    /**
     * @return list<string>
     */
    private function emailTemplateSettingKeys(): array
    {
        return array_values(array_filter(
            array_keys(CompanySettings::$casts),
            fn (string $key): bool => str_starts_with($key, 'email_template_')
                || str_starts_with($key, 'email_subject_')
                || $key === 'email_style_custom'
        ));
    }
}
