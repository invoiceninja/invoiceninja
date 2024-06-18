<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Subscription;

use App\Livewire\BillingPortal\Purchase;

class StepService
{
    public static function mapToClassNames(string $steps): array
    {
        $classes = collect(Purchase::$dependencies)->mapWithKeys(fn ($dependency, $class) => [$dependency['id'] => $class])->toArray();

        return array_map(fn ($step) => $classes[$step], explode(',', $steps));
    }

    public static function check(array $steps): array
    {
        $dependencies = Purchase::$dependencies;
        $step_order = array_flip($steps);
        $errors = [];

        foreach ($steps as $step) {
            $dependent = $dependencies[$step]['dependencies'] ?? [];

            if (!empty($dependent) && !array_intersect($dependent, $steps)) {
                $errors[] = ctrans('texts.step_dependency_fail', [
                    'step' => ctrans('texts.' . self::mapClassNameToString($step)),
                    'dependencies' => implode(', ', array_map(fn ($dependency) => ctrans('texts.' . self::mapClassNameToString($dependency)), $dependent)),
                ]);
            }

            foreach ($dependent as $dependency) {
                if (in_array($dependency, $steps) && $step_order[$dependency] > $step_order[$step]) {
                    $errors[] = ctrans('texts.step_dependency_order_fail', [
                        'step' => ctrans('texts.' . self::mapClassNameToString($step)),
                        'dependency' => implode(', ', array_map(fn ($dependency) => ctrans('texts.' . self::mapClassNameToString($dependency)), $dependent)),
                    ]);
                }
            }
        }

        $auth = collect($dependencies)
            ->filter(fn ($dependency) => str_starts_with($dependency['id'], 'auth.'))
            ->keys()
            ->toArray();

        if (count(array_intersect($auth, $steps)) === 0) {
            $errors[] = ctrans('texts.step_authentication_fail');
        }

        return $errors;
    }

    public static function mapClassNameToString(string $class): string
    {
        $classes = collect(Purchase::$dependencies)->mapWithKeys(fn ($dependency, $class) => [$class => $dependency['id']])->toArray();

        return $classes[$class];
    }
}
