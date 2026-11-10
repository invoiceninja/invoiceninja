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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;
use App\DataMapper\InvoiceItem;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Yaml\Yaml;

/**
 * The transformers in app/Transformers decide what the API returns, so every
 * response schema in openapi/api-docs.yaml must list exactly the fields its
 * transformer emits, typed as the transformer casts them.
 */
#[CoversNothing]
class OpenApiTransformerTest extends TestCase
{
    /** Transformer => the component schema documenting its output. */
    private const SCHEMAS = [
        'AccountTransformer' => 'Account',
        'AuthenticatedCompanyUserTransformer' => 'CompanyUser',
        'ActivityTransformer' => 'Activity',
        'BankIntegrationTransformer' => 'BankIntegration',
        'BankTransactionRuleTransformer' => 'BankTransactionRule',
        'BankTransactionTransformer' => 'BankTransaction',
        'ClientContactTransformer' => 'ClientContact',
        'ClientGatewayTokenTransformer' => 'ClientGatewayToken',
        'ClientTransformer' => 'Client',
        'CompanyGatewayTransformer' => 'CompanyGateway',
        'CompanyLedgerTransformer' => 'CompanyLedger',
        'CompanyTokenTransformer' => 'CompanyToken',
        'CompanyTransformer' => 'Company',
        'CreditInvitationTransformer' => 'CreditInvitation',
        'CreditTransformer' => 'Credit',
        'DesignTransformer' => 'Design',
        'DocumentTransformer' => 'Document',
        'ExpenseCategoryTransformer' => 'ExpenseCategory',
        'ExpenseTransformer' => 'Expense',
        'GroupSettingTransformer' => 'GroupSetting',
        'InvoiceInvitationTransformer' => 'InvoiceInvitation',
        'InvoiceTransformer' => 'Invoice',
        'LocationTransformer' => 'Location',
        'PaymentTermTransformer' => 'PaymentTerm',
        'PaymentTransformer' => 'Payment',
        'PaymentableTransformer' => 'Paymentable',
        'ProductTransformer' => 'Product',
        'ProjectTransformer' => 'Project',
        'PurchaseOrderTransformer' => 'PurchaseOrder',
        'QuoteTransformer' => 'Quote',
        'RecurringExpenseTransformer' => 'RecurringExpense',
        'RecurringInvoiceTransformer' => 'RecurringInvoice',
        'RecurringQuoteTransformer' => 'RecurringQuote',
        'SchedulerTransformer' => 'TaskScheduler',
        'SubscriptionTransformer' => 'Subscription',
        'SystemLogTransformer' => 'SystemLog',
        'TagTransformer' => 'Tag',
        'TaskStatusTransformer' => 'TaskStatus',
        'TaskTransformer' => 'Task',
        'TaxRateTransformer' => 'TaxRate',
        'UserTransformer' => 'User',
        'VendorContactTransformer' => 'VendorContact',
        'VendorTransformer' => 'Vendor',
        'WebhookTransformer' => 'Webhook',
    ];

    /**
     * Further schemas documenting a transformer's output: schema => transformer.
     * The *Profile transformers redact the values of nested users, clients, contacts
     * and projects but return the same keys, so the full schema documents them too.
     */
    private const ALSO_DOCUMENTED = [
        'CompanyUserRef' => 'CompanyUserTransformer',
        'UserRef' => 'UserTransformer',
        'User (profile)' => 'UserProfileTransformer',
        'Client (profile)' => 'ClientProfileTransformer',
        'ClientContact (profile)' => 'ClientContactProfileTransformer',
        'Project (profile)' => 'ProjectProfileTransformer',
    ];

    /**
     * Objects the API returns as stored rather than through a transformer:
     * schema => [classes whose public properties are its fields, fields set outside them, whether every field must be documented].
     * A client's settings override any subset of the company's.
     */
    private const DATA_MAPPERS = [
        'CompanySettings' => [[CompanySettings::class], [], true],
        'ClientSettings' => [[CompanySettings::class, ClientSettings::class], ['entity'], false],
        'FeesAndLimits' => [[FeesAndLimits::class], [], true],
        'InvoiceItem' => [[InvoiceItem::class], [], true],
    ];

    /** Transformers without a schema of their own, and why. */
    private const UNDOCUMENTED = [
        'ArraySerializer' => 'serializer, not a transformer',
        'EntityTransformer' => 'base class',
        'ClientContactLoginTransformer' => 'client portal only',
        'CompanyTokenHashedTransformer' => 'CompanyToken with the token masked',
        'GatewayTransformer' => 'statics only',
        'PaymentTypeTransformer' => 'statics only',
        'InvoiceHistoryTransformer' => 'activity history payload',
        'PurchaseOrderHistoryTransformer' => 'activity history payload',
        'PurchaseOrderInvitationTransformer' => 'not exposed by an /api/v1 response',
        'QuoteInvitationTransformer' => 'shares the InvoiceInvitation schema',
        'RecurringInvoiceInvitationTransformer' => 'shares the InvoiceInvitation schema',
        'RecurringQuoteInvitationTransformer' => 'shares the InvoiceInvitation schema',
    ];

    /** Keys built at runtime from the related record's type, all hashed ids. */
    private const DYNAMIC_KEYS = [
        'CompanyLedgerTransformer' => ['client_id', 'credit_id', 'invoice_id', 'payment_id'],
        'PaymentableTransformer' => ['invoice_id', 'credit_id'],
    ];

    /** (array) is left out: an associative array is serialized as an object. */
    private const CASTS = [
        Expr\Cast\Int_::class => 'integer',
        Expr\Cast\Double::class => 'number',
        Expr\Cast\Bool_::class => 'boolean',
        Expr\Cast\String_::class => 'string',
    ];

    private static ?array $schemas = null;

    public static function transformers(): array
    {
        $rows = [];

        foreach (self::SCHEMAS as $transformer => $schema) {
            $rows[$transformer] = [$transformer, $schema];
        }

        foreach (self::ALSO_DOCUMENTED as $schema => $transformer) {
            $rows["{$transformer} as {$schema}"] = [$transformer, $schema];
        }

        return $rows;
    }

    public static function dataMappers(): array
    {
        return array_combine(array_keys(self::DATA_MAPPERS), array_map(fn ($s) => [$s], array_keys(self::DATA_MAPPERS)));
    }

    #[DataProvider('dataMappers')]
    public function testSchemaMatchesDataMapper(string $schema_name): void
    {
        [$classes, $fields, $complete] = self::DATA_MAPPERS[$schema_name];

        foreach ($classes as $class) {
            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if (! $property->isStatic()) {
                    $fields[] = $property->getName();
                }
            }
        }

        $documented = array_keys($this->schemas()[$schema_name]['properties'] ?? []);

        $problems = array_map(fn ($p) => "{$p} is documented but {$schema_name} never has it", array_values(array_diff($documented, $fields)));

        if ($complete) {
            $problems = array_merge($problems, array_map(fn ($f) => "{$f} is not documented", array_values(array_diff($fields, $documented))));
        }

        $this->assertSame([], $problems, "{$schema_name} does not match its class");
    }

    public function testEveryTransformerIsAccountedFor(): void
    {
        $files = array_map(fn ($f) => basename($f, '.php'), glob(dirname(__DIR__, 2) . '/app/Transformers/*.php'));

        $this->assertEqualsCanonicalizing($files, array_unique(array_merge(array_keys(self::SCHEMAS), array_values(self::ALSO_DOCUMENTED), array_keys(self::UNDOCUMENTED))));
    }

    #[DataProvider('transformers')]
    public function testSchemaMatchesTransformer(string $transformer, string $schema_name): void
    {
        $properties = $this->schemas()[preg_replace('/ \(.*\)$/', '', $schema_name)]['properties'] ?? [];
        [$fields, $default_includes, $available_includes] = $this->parse($transformer);
        $fields += array_fill_keys(self::DYNAMIC_KEYS[$transformer] ?? [], 'string');

        $problems = [];

        foreach (array_keys($fields + array_flip($default_includes)) as $field) {
            if (! isset($properties[$field])) {
                $problems[] = "{$field} is returned by {$transformer} but not documented";
            }
        }

        // Request-only inputs belong in the {Schema}Request schemas, not here.
        // A profile transformer offers fewer includes than the one whose schema it shares.
        foreach (str_ends_with($schema_name, ' (profile)') ? [] : array_keys($properties) as $property) {
            if (! array_key_exists($property, $fields) && ! in_array($property, $default_includes) && ! in_array($property, $available_includes)) {
                $problems[] = "{$property} is documented but {$transformer} never returns it";
            }
        }

        foreach ($fields as $field => $type) {
            $documented = $properties[$field]['type'] ?? null;

            if ($type !== null && $documented !== null && $documented !== $type) {
                $problems[] = "{$field} is documented as {$documented} but {$transformer} returns {$type}";
            }
        }

        $this->assertSame([], $problems, "{$schema_name} does not match {$transformer}");
    }

    /** OpenAPI forbids a property that is both readOnly and writeOnly. */
    public function testNoPropertyIsBothReadOnlyAndWriteOnly(): void
    {
        $both = [];
        $walk = function (mixed $node, string $at) use (&$walk, &$both) {
            if (! is_array($node)) {
                return;
            }

            if (($node['readOnly'] ?? false) && ($node['writeOnly'] ?? false)) {
                $both[] = $at;
            }

            foreach ($node as $key => $child) {
                $walk($child, "{$at}.{$key}");
            }
        };
        $walk(Yaml::parseFile(dirname(__DIR__, 2) . '/openapi/api-docs.yaml'), 'spec');

        $this->assertSame([], $both);
    }

    /**
     * The keys transform() returns, mapped to the type their cast implies (null when uncast),
     * plus the default and available includes.
     *
     * @return array{array<string, ?string>, string[], string[]}
     */
    private function parse(string $transformer): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse(file_get_contents(dirname(__DIR__, 2) . "/app/Transformers/{$transformer}.php"));
        $finder = new NodeFinder();

        $class = $finder->findFirstInstanceOf($ast, Stmt\Class_::class);
        $transform = $class->getMethod('transform');

        // A subclass such as ClientProfileTransformer inherits transform() and the includes it doesn't redeclare.
        $parent_name = $class->extends?->toString();
        $parent = $parent_name && $parent_name !== 'EntityTransformer' ? $this->parse($parent_name) : [[], [], []];

        $fields = $transform ? [] : $parent[0];

        // Arrays built in transform(): the returned literal, or $data = [...] plus $data['key'] = ... assignments,
        // or array_merge(parent::transform(...), [...]) overriding some of the parent's values.
        foreach ($transform ? $finder->find($transform->stmts, fn (Node $n) => $n instanceof Stmt\Return_ || $n instanceof Expr\Assign) : [] as $node) {
            if ($node instanceof Expr\Assign && $node->var instanceof Expr\ArrayDimFetch && $node->var->dim instanceof Node\Scalar\String_) {
                $fields[$node->var->dim->value] = $this->type($node->expr);
                continue;
            }

            $arrays = [];
            if ($node->expr instanceof Expr\Array_ && ($node instanceof Stmt\Return_ || $node->var instanceof Expr\Variable)) {
                $arrays = [$node->expr];
            } elseif ($node instanceof Stmt\Return_ && $node->expr instanceof Expr\FuncCall && $node->expr->name instanceof Node\Name && $node->expr->name->toString() === 'array_merge') {
                foreach ($node->expr->args as $arg) {
                    if ($arg->value instanceof Expr\StaticCall && $arg->value->class instanceof Node\Name && $arg->value->class->toString() === 'parent') {
                        $fields += $parent[0];
                    } elseif ($arg->value instanceof Expr\Array_) {
                        $arrays[] = $arg->value;
                    }
                }
            }

            foreach ($arrays as $array) {
                foreach ($array->items as $item) {
                    if ($item?->key instanceof Node\Scalar\String_) {
                        $fields[$item->key->value] = $this->type($item->value);
                    }
                }
            }
        }

        $includes = fn (string $name, int $inherited) => ($property = $class->getProperty($name))
            ? array_map(fn ($item) => $item->value->value, $property->props[0]->default?->items ?? [])
            : $parent[$inherited];

        return [$fields, $includes('defaultIncludes', 1), $includes('availableIncludes', 2)];
    }

    private function type(Expr $expr): ?string
    {
        if (isset(self::CASTS[$expr::class])) {
            return self::CASTS[$expr::class];
        }

        if ($expr instanceof Expr\MethodCall && $expr->name instanceof Node\Identifier && $expr->name->name === 'encodePrimaryKey') {
            return 'string';
        }

        return match (true) {
            $expr instanceof Node\Scalar\String_ => 'string',
            $expr instanceof Node\Scalar\Int_ => 'integer',
            $expr instanceof Node\Scalar\Float_ => 'number',
            default => null,
        };
    }

    private function schemas(): array
    {
        return self::$schemas ??= Yaml::parseFile(dirname(__DIR__, 2) . '/openapi/api-docs.yaml')['components']['schemas'];
    }
}
