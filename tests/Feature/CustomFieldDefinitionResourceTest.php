<?php

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Panel;
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

    expect($entityOptions->invoke(null))->toBe(['contact' => 'Contact'])
        ->and(CustomFieldDefinitionResource::getModel())->toBe(config('custom-fields.models.definition'))
        ->and(CustomFieldDefinitionResource::getNavigationGroup())->toBe('Settings')
        ->and($typeConfigFields->invoke(null, 'string')[0])->toBeInstanceOf(TextInput::class)
        ->and($typeConfigFields->invoke(null, 'boolean')[0])->toBeInstanceOf(Select::class)
        ->and($typeConfigFields->invoke(null, 'time_range')[1])->toBeInstanceOf(Toggle::class)
        ->and($typeConfigFields->invoke(null, 'select')[0])->toBeInstanceOf(Repeater::class);
});
