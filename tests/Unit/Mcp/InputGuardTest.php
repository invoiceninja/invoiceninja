<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Mcp;

use App\Mcp\InputGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InputGuardTest extends TestCase
{
    public static function unsafeInputs(): array
    {
        $cases = [];
        foreach (["action\0ignored", 'action[ignored]', 'include[0]', "include\0ignored", 'showAccountUsers[0]', 'include.static', 'include static'] as $key) {
            $cases[] = ['query', [$key => 'download']];
        }
        foreach (['user. companies', ' user . company_tokens ', 'user.CompanyTokens', 'clients,user.\tcompanies', ['clients', 'user. companies'], ['clients,user.companies'], 'user:limit(1).companies', 'user.%63ompanies', 'user..companies', null, 12, [['companies']]] as $include) {
            foreach (['query', 'data'] as $source) {
                $cases[] = [$source, ['include' => $include]];
            }
        }
        foreach ([' download ', "\tBULK_DOWNLOAD\n", 'bulk_ print', 'delivery_note', 'print', 'template', 'history', 'new_future_action', null, ['archive'], 1] as $action) {
            foreach (['parameters', 'query', 'data'] as $source) {
                $cases[] = [$source, ['action' => $action]];
            }
        }
        return $cases;
    }

    #[DataProvider('unsafeInputs')]
    public function testRejectsAmbiguousOrUnsupportedInputsBeforeDispatch(string $source, array $input): void
    {
        $args = ['parameters' => [], 'query' => [], 'data' => []];
        $args[$source] = $input;
        $this->expectException(InvalidArgumentException::class);
        (new InputGuard())->normalize(['uri' => 'api/v1/invoices/bulk', 'parameters' => []], ...array_values($args));
    }

    public function testForwardsExactlyTheCanonicalValuesThatWereChecked(): void
    {
        [$parameters, $query, $data] = (new InputGuard())->normalize(
            ['uri' => 'api/v1/invoices/{invoice}/{action}', 'parameters' => ['invoice', 'action']],
            ['invoice' => 'hashed-id', 'action' => ' MARK_SENT '],
            ['action' => 'mark_sent', 'include' => " client . contacts , client. contacts\t"],
            ['action' => "mark_sent\n", 'include' => [' client ', 'client.contacts']]
        );
        $this->assertSame('mark_sent', $parameters['action']);
        $this->assertSame('mark_sent', $data['action']);
        $this->assertSame('client.contacts', $query['include']);
        $this->assertSame('client,client.contacts', $data['include']);
    }

    public function testRejectsConflictingActionSources(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InputGuard())->normalize(['uri' => 'api/v1/invoices/bulk', 'parameters' => []], [], ['action' => 'delete'], ['action' => 'archive']);
    }

    public function testActionCannotBeInjectedIntoAnUnrelatedOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InputGuard())->normalize(['uri' => 'api/v1/invoices', 'parameters' => []], [], ['action' => 'archive'], []);
    }

    public function testActionFromAnotherDomainCannotReachADynamicRepositoryMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InputGuard())->normalize(['uri' => 'api/v1/recurring_quotes/bulk', 'parameters' => []], [], [], ['action' => 'set_tax_id']);
    }

    public static function unimplementedActions(): array
    {
        return [
            ['invoices/{invoice}/{action}', ['invoice', 'action'], 'email'],
            ['invoices/{invoice}/{action}', ['invoice', 'action'], 'send_email'],
            ['recurring_invoices/bulk', [], 'auto_bill'],
            ['recurring_invoices/bulk', [], 'auto_bill_enabled'],
        ];
    }

    #[DataProvider('unimplementedActions')]
    public function testRejectsActionsNotImplementedByTheSelectedRestHandler(string $path, array $parameters, string $action): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InputGuard())->normalize(['uri' => 'api/v1/'.$path, 'parameters' => $parameters], [], [], ['action' => $action]);
    }

    public function testInvoiceBulkEmailRemainsAvailable(): void
    {
        [, , $data] = (new InputGuard())->normalize(['uri' => 'api/v1/invoices/bulk', 'parameters' => []], [], [], ['action' => 'email']);
        $this->assertSame('email', $data['action']);
    }
}
