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

namespace App\Http\Requests\Import;

use App\Http\Requests\Request;
use Illuminate\Validation\Validator;

class ImportJsonRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $is_chunked = filled($this->input('metadata'));

        if ($is_chunked) {
            return [
                'metadata' => ['required', 'string', 'json'],
                'file' => ['required', 'file', 'max:10120'], // 5MB, matches controller cap
            ];
        }

        return [
            'files' => ['required', 'file', 'mimes:zip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! filled($this->input('metadata'))) {
                return;
            }
            $metadata = json_decode($this->input('metadata'), true);
            if (! is_array($metadata)) {
                return; 
            }
            foreach (['fileHash', 'fileName', 'totalChunks', 'currentChunk'] as $key) {
                if (! array_key_exists($key, $metadata)) {
                    $validator->errors()->add('metadata', "Missing metadata field: {$key}");
                }
            }
            if (isset($metadata['fileName']) && ! preg_match('/\.zip$/i', basename((string) $metadata['fileName']))) {
                $validator->errors()->add('metadata', 'Import filename must end with .zip');
            }
            if (isset($metadata['fileHash']) && ! preg_match('/^[a-zA-Z0-9]+$/', (string) $metadata['fileHash'])) {
                $validator->errors()->add('metadata', 'Invalid file hash format');
            }
            if (isset($metadata['currentChunk'], $metadata['totalChunks'])) {
                $current = (int) $metadata['currentChunk'];
                $total = (int) $metadata['totalChunks'];

                if ($total < 1 || $total > 1000) {
                    $validator->errors()->add('metadata', 'Invalid chunk metadata');
                } elseif ($current < 0 || $current >= $total) {
                    $validator->errors()->add('metadata', 'Invalid chunk metadata');
                }
            }
        });
    }
}
