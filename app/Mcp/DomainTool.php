<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class DomainTool extends Tool
{
    public function __construct(string $domain)
    {
        $this->name = $domain;
        $this->title = ucwords(str_replace('_', ' ', $domain));
        $operations = app(Catalog::class)->available($domain);
        $details = [];
        foreach ($operations as $operation => $spec) {
            $details[] = $operation.'('.implode(', ', $spec['parameters']).')';
        }
        $this->description = $this->title.' REST operations: '.implode('; ', $details).'. Pass path parameters as parameters, REST filters as query, and the REST JSON payload as data. Original controller validation and permissions apply. Mutating operations may send mail or invoke configured gateways.';
    }

    public function shouldRegister(): bool
    {
        return app(Catalog::class)->available($this->name) !== [];
    }

    public function annotations(): array
    {
        $specs = app(Catalog::class)->available($this->name);
        return ['readOnlyHint' => $specs !== [] && !in_array(false, array_column($specs, 'read_only'), true), 'destructiveHint' => true, 'openWorldHint' => true];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->enum(array_keys(app(Catalog::class)->available($this->name)))->required(),
            'parameters' => $schema->object()->description('Required path parameter names are listed next to each operation. Use hashed entity IDs.'),
            'query' => $schema->object()->description('REST query filters, includes, page and per_page. Do not pass credentials.'),
            'data' => $schema->object()->description('JSON body in the existing REST API format; validated by the original FormRequest.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $catalog = app(Catalog::class);
        $request->validate([
            'operation' => ['required', 'string', Rule::in(array_keys($catalog->available($this->name)))],
            'parameters' => ['sometimes', 'array'], 'query' => ['sometimes', 'array'], 'data' => ['sometimes', 'array'],
        ]);
        $operation = $request->get('operation');
        // Never treat discovery as authorization; recheck restrictions on direct invocation.
        if (!$catalog->enabled($this->name, $operation)) {
            return Response::error('Tool disabled.');
        }
        $spec = $catalog->available($this->name)[$operation];
        return app(Dispatcher::class)->dispatch($spec, $request);
    }
}
