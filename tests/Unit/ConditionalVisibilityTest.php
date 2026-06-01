<?php

use Yezper\LaravelCustomFieldsFilament\Rules\ConditionalVisibility;

it('accepts valid conditional visibility rule trees', function (): void {
    $failures = [];

    (new ConditionalVisibility)->validate('conditional_visibility', [
        'operator' => 'and',
        'conditions' => [
            ['field' => 'plan', 'op' => 'in', 'value' => ['gold', 'vip']],
            ['field' => 'active', 'op' => 'truthy'],
        ],
    ], function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe([]);
});

it('rejects invalid conditional visibility rule shapes', function (mixed $value, string $message): void {
    $failures = [];

    (new ConditionalVisibility)->validate('conditional_visibility', $value, function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures[0] ?? null)->toContain($message);
})->with([
    'not array' => ['nope', 'valid conditional visibility data'],
    'bad root operator' => [['operator' => 'xor', 'conditions' => [['field' => 'a', 'op' => 'truthy']]], 'Invalid operator'],
    'missing value' => [['conditions' => [['field' => 'a', 'op' => 'eq']]], 'requires'],
    'forbidden value' => [['conditions' => [['field' => 'a', 'op' => 'truthy', 'value' => true]]], 'must not have'],
    'in value not array' => [['conditions' => [['field' => 'a', 'op' => 'in', 'value' => 'x']]], 'must be an array'],
]);
