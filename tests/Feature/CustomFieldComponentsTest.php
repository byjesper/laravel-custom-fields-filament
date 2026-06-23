<?php

use ByJesper\LaravelCustomFields\Models\CustomFieldDefinition;
use ByJesper\LaravelCustomFieldsFilament\Components\CustomFieldForm;
use ByJesper\LaravelCustomFieldsFilament\Components\CustomFieldInfolist;
use ByJesper\LaravelCustomFieldsFilament\Components\CustomFieldTableColumn;
use ByJesper\LaravelCustomFieldsFilament\Components\CustomFieldTableFilter;
use ByJesper\LaravelCustomFieldsFilament\Support\CustomFieldDisplayResolver;
use ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures\Contact;
use ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures\CustomFieldFormDataHarness;
use ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures\Organization;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry as InfolistTextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

it('preserves golden table display states through the shared resolver', function (): void {
    Organization::query()->create(['id' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001', 'name' => 'Acme']);

    $resolver = app(CustomFieldDisplayResolver::class);
    $relationship = filamentDefinition('organization', 'relationship', [
        'target_entity' => 'organization',
        'display_field' => 'name',
    ]);
    $record = new Contact([
        'custom_field_values' => ['organization' => ['value' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001']],
    ]);
    $resolver->primeRelationshipLabels($relationship, [$record]);

    expect($resolver->formatForTable(filamentDefinition('segment', 'select', [
        'options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]],
    ]), 'vip'))->toBe('VIP')
        ->and($resolver->formatForTable(filamentDefinition('tags', 'multi_select', [
            'options' => [['value' => 'vip', 'label' => ['en' => 'VIP']]],
        ]), ['vip']))->toBe('VIP')
        ->and($resolver->formatForTable($relationship, '018f11f2-7ad2-72f1-9ea1-1867d37a3001'))->toBe('Acme')
        ->and($resolver->formatForTable(filamentDefinition('window', 'date_range'), ['start' => '2026-06-01', 'end' => '2026-06-30']))
        ->toBe('2026-06-01 - 2026-06-30')
        ->and($resolver->formatForTable(filamentDefinition('payload', 'json'), ['a' => 1]))->toBe('{"a":1}');
});

it('renders persisted form bridge values through type-aware infolist entries', function (): void {
    Organization::query()->create(['id' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001', 'name' => 'Acme']);

    $definitions = [];

    foreach ([
        'name' => 'string',
        'notes' => 'text',
        'count' => 'integer',
        'amount' => 'decimal',
        'enabled' => 'boolean',
        'starts_on' => 'date',
        'starts_at' => 'datetime',
        'starts_time' => 'time',
        'segment' => 'select',
        'status' => 'enum',
        'tags' => 'multi_select',
        'organization' => 'relationship',
        'metadata' => 'json',
        'date_window' => 'date_range',
        'datetime_window' => 'datetime_range',
        'time_window' => 'time_range',
    ] as $name => $type) {
        $definitions[$name] = filamentDefinition($name, $type, match ($type) {
            'select', 'enum', 'multi_select' => ['options' => [['value' => 'vip', 'label' => ['en' => 'VIP']], ['value' => 'gold', 'label' => ['en' => 'Gold']]]],
            'relationship' => ['target_entity' => 'organization', 'display_field' => 'name'],
            'decimal' => ['scale' => 2],
            default => [],
        });
    }

    $custom = [
        'name' => 'Ada',
        'notes' => '<strong>Safe</strong>',
        'count' => 7,
        'amount' => 2.5,
        'enabled' => true,
        'starts_on' => '2026-06-01',
        'starts_at' => '2026-06-01 09:30:00',
        'starts_time' => '09:30:00',
        'segment' => 'vip',
        'status' => 'gold',
        'tags' => ['vip', 'gold'],
        'organization' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001',
        'metadata' => ['key' => 'value'],
        'date_window' => ['start' => '2026-06-01', 'end' => '2026-06-30'],
        'datetime_window' => ['start' => '2026-06-01 09:30:00', 'end' => '2026-06-30 17:00:00'],
        'time_window' => ['start' => '09:00:00', 'end' => '17:00:00'],
    ];
    $harness = new CustomFieldFormDataHarness;
    $contact = Contact::query()->create([
        'id' => fake()->uuid(),
        'custom_field_values' => $harness->build($custom),
    ]);

    $entryFor = new ReflectionMethod(CustomFieldInfolist::class, 'entryFor');
    $entryFor->setAccessible(true);
    $resolver = app(CustomFieldDisplayResolver::class);
    $entries = [];

    foreach ($definitions as $name => $definition) {
        $entries["custom_field_{$name}"] = $entryFor->invoke(null, $definition, $contact, $resolver);
    }

    expect($entries['custom_field_name'])->toBeInstanceOf(InfolistTextEntry::class)
        ->and($entries['custom_field_enabled'])->toBeInstanceOf(IconEntry::class)
        ->and($entries['custom_field_name']->getState())->toBe('Ada')
        ->and($entries['custom_field_segment']->getState())->toBe('VIP')
        ->and($entries['custom_field_tags']->getState())->toBe(['VIP', 'Gold'])
        ->and($entries['custom_field_organization']->getState())->toBe('Acme')
        ->and($entries['custom_field_metadata']->getState())->toBe("{\n    \"key\": \"value\"\n}")
        ->and($entries['custom_field_date_window']->getState())->toBe('2026-06-01 - 2026-06-30')
        ->and($entries['custom_field_datetime_window']->getState())->toBe('2026-06-01 09:30 - 2026-06-30 17:00')
        ->and($entries['custom_field_time_window']->getState())->toBe('09:00:00 - 17:00:00');
});

it('shares form grouping and returns an empty infolist without definitions', function (): void {
    expect(CustomFieldInfolist::make('contact'))->toBe([]);

    filamentDefinition('ungrouped', 'string', [], ['sort_order' => 1]);
    filamentDefinition('finance', 'decimal', [], ['group_level_1' => 'Finance', 'group_level_2' => 'Revenue', 'sort_order' => 2]);
    filamentDefinition('inactive', 'string', [], ['is_active' => false]);

    $form = CustomFieldForm::make('contact');
    $infolist = CustomFieldInfolist::make('contact');

    expect($form)->toHaveCount(2)
        ->and($infolist)->toHaveCount(2)
        ->and($form[0]->getHeading())->toBe($infolist[0]->getHeading())
        ->and($form[1]->getHeading())->toBe($infolist[1]->getHeading());
});

it('batch resolves relationship labels once for a table page of records', function (): void {
    filamentDefinition('organization', 'relationship', [
        'target_entity' => 'organization',
        'display_field' => 'name',
    ]);
    Organization::query()->create(['id' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001', 'name' => 'Acme']);
    Organization::query()->create(['id' => '018f11f2-7ad2-72f1-9ea1-1867d37a3002', 'name' => 'Globex']);

    foreach (['018f11f2-7ad2-72f1-9ea1-1867d37a3001', '018f11f2-7ad2-72f1-9ea1-1867d37a3002', '018f11f2-7ad2-72f1-9ea1-1867d37a3001'] as $organizationId) {
        Contact::query()->create([
            'id' => fake()->uuid(),
            'custom_field_values' => ['organization' => ['value' => $organizationId]],
        ]);
    }

    $relationshipQueries = [];
    DB::listen(function ($query) use (&$relationshipQueries): void {
        if (str_contains($query->sql, 'cf_filament_organizations')) {
            $relationshipQueries[] = $query->sql;
        }
    });

    $records = Contact::query()->get();
    $livewire = Mockery::mock(HasTable::class);
    $livewire->shouldReceive('getTableRecords')->andReturn($records);
    $livewire->shouldReceive('getTableRecordKey')->andReturnUsing(fn (Contact $record): string => (string) $record->getKey());
    $table = Table::make($livewire);
    $column = CustomFieldTableColumn::make('contact')[0]->table($table);
    $states = [];

    foreach ($records as $record) {
        $column->record($record);
        $states[] = $column->getState();
    }

    expect($states)->toBe(['Acme', 'Globex', 'Acme']);

    expect($relationshipQueries)->toHaveCount(1);
});

it('resolves relationship labels when the display field is an accessor over sibling columns', function (): void {
    // display_name has no stored value; its accessor composes first_name + last_name. pluck()ing the
    // single column would mutate a partial model and collapse the accessor fallback to an empty string.
    Organization::query()->create([
        'id' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001',
        'name' => 'Acme',
        'first_name' => 'Manager',
        'last_name' => 'Person',
    ]);

    $resolver = app(CustomFieldDisplayResolver::class);
    $relationship = filamentDefinition('manager', 'relationship', [
        'target_entity' => 'organization',
        'display_field' => 'display_name',
    ]);
    $record = new Contact([
        'custom_field_values' => ['manager' => ['value' => '018f11f2-7ad2-72f1-9ea1-1867d37a3001']],
    ]);

    $resolver->primeRelationshipLabels($relationship, [$record]);

    expect($resolver->formatForTable($relationship, '018f11f2-7ad2-72f1-9ea1-1867d37a3001'))->toBe('Manager Person');
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
