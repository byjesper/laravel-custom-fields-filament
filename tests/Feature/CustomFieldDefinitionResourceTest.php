<?php

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Panel;
use Filament\Schemas\Components\Callout;
use Yezper\LaravelCustomFieldsFilament\CustomFieldsPlugin;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas\CustomFieldDefinitionForm;

it('registers the definition resource on the Filament panel plugin', function (): void {
    $panel = Panel::make()->id('admin')->path('admin');

    CustomFieldsPlugin::make()->register($panel);

    expect($panel->getResources())->toContain(CustomFieldDefinitionResource::class);
});

it('exposes definition resource metadata and type specific form pieces', function (): void {
    $entityOptions = new ReflectionMethod(CustomFieldDefinitionForm::class, 'entityOptions');
    $entityOptions->setAccessible(true);
    $typeConfigFields = new ReflectionMethod(CustomFieldDefinitionForm::class, 'typeConfigFields');
    $typeConfigFields->setAccessible(true);
    $validationRuleFields = new ReflectionMethod(CustomFieldDefinitionForm::class, 'validationRuleFields');
    $validationRuleFields->setAccessible(true);

    $dateRules = $validationRuleFields->invoke(null, 'date');
    $datetimeRules = $validationRuleFields->invoke(null, 'datetime');
    $timeRules = $validationRuleFields->invoke(null, 'time');

    expect($entityOptions->invoke(null))->toBe(['contact' => 'Contact'])
        ->and(CustomFieldDefinitionResource::getModel())->toBe(config('custom-fields.models.definition'))
        ->and(CustomFieldDefinitionResource::getNavigationGroup())->toBe('Settings')
        ->and($typeConfigFields->invoke(null, 'string')[0])->toBeInstanceOf(TextInput::class)
        ->and($typeConfigFields->invoke(null, 'boolean')[0])->toBeInstanceOf(Select::class)
        ->and($typeConfigFields->invoke(null, 'date')[0])->toBeInstanceOf(Callout::class)
        ->and($typeConfigFields->invoke(null, 'time_range')[1])->toBeInstanceOf(Toggle::class)
        ->and($typeConfigFields->invoke(null, 'select')[0])->toBeInstanceOf(Repeater::class)
        ->and($dateRules[2])->toBeInstanceOf(DatePicker::class)
        ->and($dateRules[3])->toBeInstanceOf(TextInput::class)
        ->and($datetimeRules[2])->toBeInstanceOf(DateTimePicker::class)
        ->and($timeRules[2])->toBeInstanceOf(TimePicker::class)
        ->and($timeRules)->toHaveCount(5);
});

it('normalizes temporal validation form state for persistence', function (): void {
    $normalized = CustomFieldDefinitionForm::normalizeFormData([
        'field_type' => 'date',
        'validation_rules' => [
            'required' => true,
            'min' => ['type' => 'relative', 'offset' => '-2', 'unit' => 'weeks'],
            'max' => ['type' => 'fixed', 'value' => '2026-06-30'],
        ],
    ]);

    expect($normalized['validation_rules'])->toBe([
        'required' => true,
        'min' => ['type' => 'relative', 'anchor' => 'today', 'offset' => -2, 'unit' => 'weeks'],
        'max' => ['type' => 'fixed', 'value' => '2026-06-30'],
    ]);
});

it('prepares legacy temporal fixed-bound rules for the form', function (): void {
    $prepared = CustomFieldDefinitionForm::prepareFormDataForFill([
        'field_type' => 'time',
        'validation_rules' => [
            'min' => '08:00',
            'max' => '17:00',
        ],
    ]);

    expect($prepared['validation_rules'])->toBe([
        'min' => ['type' => 'fixed', 'value' => '08:00'],
        'max' => ['type' => 'fixed', 'value' => '17:00'],
    ]);
});
