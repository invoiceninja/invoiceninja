<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

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
        $openApi = app(OpenApi::class);
        foreach ($operations as $operation => $spec) {
            $summary = $openApi->operation($spec)['summary'] ?? 'See REST API (not yet described in OpenAPI)';
            $details[] = $operation.'('.implode(', ', $spec['parameters']).'): '.mb_substr($summary, 0, 100);
        }
        $this->description = $this->title.' REST operations: '.implode('; ', $details).'. Pass path parameters as parameters, REST filters as query, and the REST JSON payload as data. Original controller validation and permissions apply. Mutating operations may send mail or invoke configured gateways.';
    }

    public function shouldRegister(): bool
    {
        return app(Catalog::class)->available($this->name) !== [];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['inputSchema'] = app(OpenApi::class)->inputSchema(app(Catalog::class)->available($this->name));
        return $result;
    }

    public function annotations(): array
    {
        $specs = app(Catalog::class)->available($this->name);
        return ['readOnlyHint' => $specs !== [] && !in_array(false, array_column($specs, 'read_only'), true), 'destructiveHint' => true, 'openWorldHint' => true];
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
