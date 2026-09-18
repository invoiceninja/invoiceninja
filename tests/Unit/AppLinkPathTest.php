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

namespace Tests\Unit;

use App\Utils\AppLinkPath;
use Tests\TestCase;

/**
 * The route translation behind shared record links — invoiceninja/flutter#144.
 * Both rules fail silently if they regress: the link still resolves, it just
 * lands on an empty page or a 404.
 *
 * @see \App\Utils\AppLinkPath
 */
class AppLinkPathTest extends TestCase
{
    public function testARecordGetsTheEditSuffixTheWebClientNeeds()
    {
        // A bare :id matches the web client's shell and renders an empty page.
        $this->assertSame(
            'invoices/Opnel5aKBz/edit',
            AppLinkPath::forWebClient('invoices/Opnel5aKBz')
        );
        $this->assertSame(
            'settings/bank_accounts/Wpmbk5ezJn/edit',
            AppLinkPath::forWebClient('settings/bank_accounts/Wpmbk5ezJn')
        );
    }

    public function testAListRouteIsNotSuffixed()
    {
        $this->assertSame('clients', AppLinkPath::forWebClient('clients'));

        // Rules out counting segments: as many as `clients/<id>`, and a list.
        $this->assertSame(
            'settings/bank_accounts',
            AppLinkPath::forWebClient('settings/bank_accounts')
        );
    }

    public function testAnExistingEditSuffixIsNotDoubled()
    {
        // The apps emit this for an entity with no detail screen.
        $this->assertSame(
            'settings/bank_accounts/transaction_rules/Wp1/edit',
            AppLinkPath::forWebClient('settings/bank_accounts/transaction_rules/Wp1/edit')
        );
    }

    public function testANestedRootWinsOverItsParent()
    {
        // Longest match first, or transaction_rules reads as a bank account id.
        $this->assertSame(
            'settings/bank_accounts/transaction_rules',
            AppLinkPath::forWebClient('settings/bank_accounts/transaction_rules')
        );
    }

    public function testScreensTheTwoClientsNameDifferently()
    {
        $this->assertSame(
            'settings/gateways/Wp1/edit',
            AppLinkPath::forWebClient('settings/company_gateways/Wp1')
        );
        $this->assertSame(
            'settings/subscriptions/Wp1/edit',
            AppLinkPath::forWebClient('settings/payment_links/Wp1')
        );
        $this->assertSame(
            'settings/integrations/api_tokens/Wp1/edit',
            AppLinkPath::forWebClient('settings/account_management/integrations/api_tokens/Wp1')
        );
    }

    public function testAViewOnlyRootKeepsItsBareId()
    {
        // React renders a real show page at `clients/:id` and `vendors/:id`, and
        // its editor needs `edit_*` — suffixing sends a view-only user to a 401.
        $this->assertSame('clients/Wp1', AppLinkPath::forWebClient('clients/Wp1'));
        $this->assertSame('vendors/Wp1', AppLinkPath::forWebClient('vendors/Wp1'));

        // Still honoured when the app asked for the editor by name.
        $this->assertSame('clients/Wp1/edit', AppLinkPath::forWebClient('clients/Wp1/edit'));
    }

    public function testAnUnknownRootIsPassedThroughRatherThanGuessed()
    {
        // A wrong guess is a 404; an untouched path lands on a section.
        $this->assertSame('widgets/Wp1', AppLinkPath::forWebClient('widgets/Wp1'));
        $this->assertSame('', AppLinkPath::forWebClient(''));
    }

    public function testRepeatedSlashesCollapse()
    {
        $this->assertSame('clients/Wp1/edit', AppLinkPath::forWebClient('//clients//Wp1//'));
    }
}
