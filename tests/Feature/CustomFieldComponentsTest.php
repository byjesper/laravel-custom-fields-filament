<?php

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Validation\ValidationException;
use Yezper\LaravelCustomFields\Models\CustomFieldDefinition;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldForm;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldTableColumn;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldTableFilter;
use Yezper\LaravelCustomFieldsFilament\Tests\Fixtures\CustomFieldFormDataHarness;
use Yezper\LaravelCustomFieldsFilament\Tests\Fixtures\Organization;

beforeEach(function (): void {
    $this->resetSqliteDatabase();
    $this->createCustomFieldTables();
    $this->createEntityTables();
});

it('maps built in field definitions to type aware form components', function (string $type, string $expectedClass): void {
    $definition = filamentDefinition("field_{$type}", $type, match ($type) {
        'select', 'enum', 'multi_select' => ['options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]]],
        'relationship' => ['target_entity' => 'organization', 'display_field' => 'name'],
        default => [],
    });

    expect(CustomFieldForm::componentFor($definition))->toBeInstanceOf($expectedClass);
})->with([
    'string' => ['string', TextInput::class],
    'text' => ['text', Textarea::class],
    'integer' => ['integer', TextInput::class],
    'decimal' => ['decimal', TextInput::class],
    'boolean' => ['boolean', Toggle::class],
    'date' => ['date', DatePicker::class],
    'datetime' => ['datetime', DateTimePicker::class],
    'time' => ['time', TimePicker::class],
    'date range' => ['date_range', Fieldset::class],
    'datetime range' => ['datetime_range', Fieldset::class],
    'time range' => ['time_range', Fieldset::class],
    'select' => ['select', Select::class],
    'enum' => ['enum', Select::class],
    'multi select' => ['multi_select', CheckboxList::class],
    'relationship' => ['relationship', Select::class],
    'json' => ['json', Textarea::class],
]);

it('groups generated custom field forms by definition grouping metadata', function (): void {
    filamentDefinition('ungrouped', 'string', [], ['sort_order' => 1]);
    filamentDefinition('finance', 'decimal', [], ['group_level_1' => 'Finance', 'group_level_2' => 'Revenue', 'sort_order' => 2]);

    $schema = CustomFieldForm::make('contact');

    expect($schema)->toHaveCount(2)
        ->and($schema[0])->toBeInstanceOf(Section::class)
        ->and($schema[1])->toBeInstanceOf(Section::class);
});

it('formats table column state for display oriented field types', function (): void {
    Organization::query()->create(['id' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001', 'name' => 'Acme']);

    $resolve = new ReflectionMethod(CustomFieldTableColumn::class, 'resolveValue');
    $resolve->setAccessible(true);

    expect($resolve->invoke(null, filamentDefinition('segment', 'select', [
        'options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]],
    ]), 'vip'))->toBe('VIP')
        ->and($resolve->invoke(null, filamentDefinition('tags', 'multi_select', [
            'options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]],
        ]), ['vip']))->toBe('VIP')
        ->and($resolve->invoke(null, filamentDefinition('organization', 'relationship', [
            'target_entity' => 'organization',
            'display_field' => 'name',
        ]), '018f11f2-7ad2-72f1-9ea1-1867d37a3001'))->toBe('Acme')
        ->and($resolve->invoke(null, filamentDefinition('window', 'date_range'), ['start' => '2026-06-01', 'end' => '2026-06-30']))
        ->toBe('2026-06-01 - 2026-06-30')
        ->and($resolve->invoke(null, filamentDefinition('payload', 'json'), ['a' => 1]))->toBe('{"a":1}');
});

it('builds table columns and filters for active definitions', function (): void {
    filamentDefinition('active', 'boolean');
    filamentDefinition('segment', 'select', ['options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]]]);
    filamentDefinition('tags', 'multi_select', ['options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]]]);
    filamentDefinition('starts_on', 'date');
    filamentDefinition('score', 'integer');
    filamentDefinition('name', 'string');

    $columns = CustomFieldTableColumn::make('contact');
    $filters = CustomFieldTableFilter::make('contact');

    expect($columns)->toHaveCount(6)
        ->and($columns[0])->toBeInstanceOf(IconColumn::class)
        ->and($columns[1])->toBeInstanceOf(TextColumn::class)
        ->and($filters)->toHaveCount(6)
        ->and($filters[0])->toBeInstanceOf(TernaryFilter::class)
        ->and($filters[1])->toBeInstanceOf(SelectFilter::class)
        ->and($filters[2])->toBeInstanceOf(SelectFilter::class)
        ->and($filters[3])->toBeInstanceOf(Filter::class)
        ->and($filters[4])->toBeInstanceOf(Filter::class)
        ->and($filters[5])->toBeInstanceOf(Filter::class);
});

it('shapes custom form data and remaps validation errors for Filament pages', function (): void {
    filamentDefinition('plan', 'string', [], ['validation_rules' => ['required' => true]]);

    $harness = new CustomFieldFormDataHarness;

    expect($harness->build(['plan' => 'gold', 'empty' => null]))->toBe(['plan' => ['value' => 'gold']]);

    expect(fn () => $harness->validateData([]))
        ->toThrow(ValidationException::class, 'The field is required.');
});

function filamentDefinition(string $fieldName, string $fieldType, array $config = [], array $attributes = []): CustomFieldDefinition
{
    return CustomFieldDefinition::query()->create(array_merge([
        'id' => fake()->uuid(),
        'entity_type' => 'contact',
        'field_name' => $fieldName,
        'field_label' => ['en' => str($fieldName)->headline()->toString()],
        'field_type' => $fieldType,
        'config' => $config,
        'validation_rules' => [],
        'is_active' => true,
    ], $attributes));
}
