<?php

namespace ByJesper\LaravelCustomFieldsFilament\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ConditionalVisibility implements ValidationRule
{
    private const array ALLOWED_OPS = ['eq', 'neq', 'in', 'notIn', 'truthy', 'falsy'];

    private const array VALUE_REQUIRED_OPS = ['eq', 'neq', 'in', 'notIn'];

    private const array VALUE_FORBIDDEN_OPS = ['truthy', 'falsy'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            $fail('The :attribute must be valid conditional visibility data.');

            return;
        }

        if (! in_array($value['operator'] ?? 'and', ['and', 'or'], true)) {
            $fail("Invalid operator '{$value['operator']}'. Expected 'and' or 'or'.");

            return;
        }

        $conditions = $value['conditions'] ?? null;
        if (! is_array($conditions) || $conditions === []) {
            $fail('conditions must be a non-empty array.');

            return;
        }

        foreach ($conditions as $index => $condition) {
            if (! isset($condition['field'], $condition['op'])) {
                $fail("Condition #{$index} is missing 'field' or 'op'.");

                return;
            }

            $op = $condition['op'];
            if (! in_array($op, self::ALLOWED_OPS, true)) {
                $fail("Condition #{$index} has unknown op '{$op}'.");

                return;
            }

            if (in_array($op, self::VALUE_REQUIRED_OPS, true) && ! array_key_exists('value', $condition)) {
                $fail("Condition #{$index} ({$op}) requires 'value'.");

                return;
            }

            if (in_array($op, self::VALUE_FORBIDDEN_OPS, true) && array_key_exists('value', $condition)) {
                $fail("Condition #{$index} ({$op}) must not have 'value'.");

                return;
            }

            if (in_array($op, ['in', 'notIn'], true) && ! is_array($condition['value'] ?? null)) {
                $fail("Condition #{$index} ({$op}) 'value' must be an array.");

                return;
            }
        }
    }
}
