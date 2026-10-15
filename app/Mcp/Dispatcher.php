<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class Dispatcher
{
    public function dispatch(array $spec, Request $request): Response|ResponseFactory
    {
        $parameters = $request->get('parameters', []);
        $query = $request->get('query', []);
        $data = $request->get('data', []);
        try {
            [$parameters, $query, $data] = app(InputGuard::class)->normalize($spec, $parameters, $query, $data);
        } catch (\InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
        if (array_diff(array_keys($parameters), $spec['parameters']) || array_diff($spec['parameters'], array_keys($parameters))) {
            return Response::error('Path parameters must be exactly: '.implode(', ', $spec['parameters']));
        }
        foreach ($parameters as $value) {
            if (!is_string($value) || $value === '' || preg_match('~[/\\\\?#%\x00-\x20]~', $value)) {
                return Response::error('Path parameters must be non-empty URL-safe strings.');
            }
        }
        $outer = request();
        $uri = preg_replace_callback('/\{([^}?]+)\??\}/', fn ($match) => rawurlencode($parameters[$match[1]]), $spec['uri']);
        $url = $outer->getSchemeAndHttpHost().'/'.$uri;
        if ($query) {
            $url .= '?'.http_build_query($query);
        }
        $inner = HttpRequest::create($url, $spec['method'], [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_TOKEN' => $outer->header('X-API-TOKEN'),
            'HTTP_X_API_SECRET' => $outer->header('X-API-SECRET'),
            'REMOTE_ADDR' => $outer->ip(),
        ], json_encode((object)$data, JSON_THROW_ON_ERROR));
        try {
            // A native subrequest: full HTTP middleware, route binding, FormRequest, policy,
            // controller, repository and transformer. No loopback HTTP or alternate grants.
            $response = app(Kernel::class)->handle($inner);
            $status = $response->getStatusCode();
            $content = $response->getContent();
            $body = is_string($content) ? json_decode($content, true) : null;
            if ($status >= 500) {
                return Response::error(json_encode(['status' => $status, 'message' => 'The application could not complete this operation.'], JSON_THROW_ON_ERROR));
            }
            if (!is_string($content) || strlen($content) > config('mcp.max_response_bytes') || $content !== '' && json_last_error() !== JSON_ERROR_NONE) {
                $result = [
                    'status' => $status,
                    'message' => 'The REST response is not a bounded JSON result. Use REST for files or reduce the page size.',
                    'operation_may_have_completed' => !$spec['read_only'],
                    'entity_id' => is_array($body) && is_string($body['data']['id'] ?? null) ? $body['data']['id'] : null,
                ];
                return Response::make(Response::error(json_encode($result, JSON_THROW_ON_ERROR)))->withStructuredContent($result);
            }
            $result = ['status' => $status, 'body' => $body];
            if ($status >= 400) {
                return Response::make(Response::error(json_encode($result, JSON_THROW_ON_ERROR)))->withStructuredContent($result);
            }
            return Response::structured($result);
        } finally {
            app()->instance('request', $outer);
            app('url')->setRequest($outer);
        }
    }
}
