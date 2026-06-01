<?php

use Yezper\LaravelCustomFields\Models\CustomFieldDefinition;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldForm;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldTableFilter;

beforeEach(function (): void {
    $this->resetSqliteDatabase();
    $this->createCustomFieldTables();
    $this->createEntityTables();
});

it('creates representative definitions and reflects them through Filament builders', function (): void {
    CustomFieldDefinition::query()->create([
        'id' => '018f11f2-7ad2-72f1-9ea1-1867d37a4001',
        'entity_type' => 'contact',
        'field_name' => 'plan',
        'field_label' => ['en' => 'Plan'],
        'field_type' => 'select',
        'config' => ['options' => [['value' => 'gold', 'label' => ['en' => 'Gold']]]],
        'default_value' => 'gold',
        'validation_rules' => ['required' => true],
        'is_active' => true,
    ]);
    CustomFieldDefinition::query()->create([
        'id' => '018f11f2-7ad2-72f1-9ea1-1867d37a4002',
        'entity_type' => 'contact',
        'field_name' => 'visible_when_plan',
        'field_label' => ['en' => 'Visible when plan'],
        'field_type' => 'string',
        'conditional_visibility' => [
            'operator' => 'and',
            'conditions' => [['field' => 'plan', 'op' => 'eq', 'value' => 'gold']],
        ],
        'is_active' => true,
    ]);

    expect(CustomFieldForm::make('contact'))->toHaveCount(1)
        ->and(CustomFieldTableFilter::make('contact'))->toHaveCount(2);
})->group('integration');
