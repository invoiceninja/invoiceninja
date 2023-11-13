<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Scheduler;

use App\Models\Client;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidClientIds.
 */
class ValidClientIds implements Rule
{
    use MakesHash;
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return Client::where('company_id', $user->company()->id)->whereIn('id', $this->transformKeys($value))->count() == count($value);
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'Invalid client ids';
    }
}
