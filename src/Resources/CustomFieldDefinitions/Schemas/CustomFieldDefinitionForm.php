<?php

namespace Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Yezper\LaravelCustomFields\Services\CustomFieldTypeRegistry;
use Yezper\LaravelCustomFieldsFilament\Rules\ConditionalVisibility;

class CustomFieldDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Basic information'))->schema([
                Select::make('entity_type')
                    ->options(fn (): array => self::entityOptions())
                    ->required()
                    ->disabledOn('edit'),

                TextInput::make('field_name')
                    ->required()
                    ->extraInputAttributes(['style' => 'text-transform: lowercase;'])
                    ->regex('/^[a-z0-9_]+$/')
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : strtolower($state))
                    ->helperText(__('Lowercase letters, numbers, and underscores only.'))
                    ->disabledOn('edit')
                    ->unique(
                        table: config('custom-fields.tables.definitions'),
                        column: 'field_name',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('entity_type', $get('entity_type')),
                    ),

                TextInput::make('field_label.en')
                    ->label(__('Label (English)'))
                    ->required(),

                TextInput::make('field_label.da')
                    ->label(__('Label (Danish)')),

                Select::make('field_type')
                    ->options(fn (): array => app(CustomFieldTypeRegistry::class)->options())
                    ->required()
                    ->live()
                    ->disabledOn('edit'),

                TextInput::make('group_level_1'),
                TextInput::make('group_level_2'),

                TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')->default(true),
            ]),

            Section::make(__('Type configuration'))
                ->visible(fn (Get $get): bool => filled($get('field_type')))
                ->schema(fn (Get $get): array => self::typeConfigFields($get('field_type'))),

            Section::make(__('Validation rules'))->schema([
                Toggle::make('validation_rules.required')->label(__('Required'))->default(false),
                TextInput::make('validation_rules.min')
                    ->label(__('Minimum'))
                    ->numeric()
                    ->visible(fn (Get $get): bool => in_array($get('field_type'), ['integer', 'decimal', 'string'], true)),
                TextInput::make('validation_rules.max')
                    ->label(__('Maximum'))
                    ->numeric()
                    ->visible(fn (Get $get): bool => in_array($get('field_type'), ['integer', 'decimal', 'string'], true)),
            ]),

            Section::make(__('Default value'))->schema([
                Textarea::make('default_value')
                    ->label(__('Default JSON value'))
                    ->rows(2)
                    ->rules(['nullable', 'json'])
                    ->dehydrateStateUsing(fn (?string $state): mixed => blank($state) ? null : json_decode($state, true))
                    ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : json_encode($state)),
            ]),

            Section::make(__('Conditional visibility'))->schema([
                Textarea::make('conditional_visibility')
                    ->label(__('Visibility rule JSON'))
                    ->rows(6)
                    ->rules(['nullable', 'json'])
                    ->dehydrateStateUsing(fn (?string $state): mixed => blank($state) ? null : json_decode($state, true))
                    ->rule(new ConditionalVisibility)
                    ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : json_encode($state, JSON_PRETTY_PRINT)),
            ]),
        ]);
    }

    /** @return array<string, string> */
    private static function entityOptions(): array
    {
        return collect(config('custom-fields.entities.enabled', []))
            ->mapWithKeys(fn (string $entity): array => [$entity => str($entity)->replace('_', ' ')->title()->toString()])
            ->all();
    }

    /** @return array<int, mixed> */
    private static function typeConfigFields(?string $type): array
    {
        return match ($type) {
            'string' => [
                TextInput::make('config.max_length')->label(__('Max length'))->numeric()->integer()->default(255),
            ],
            'text' => [
                TextInput::make('config.max_length')->label(__('Max length'))->numeric()->integer(),
            ],
            'integer' => [
                TextInput::make('config.min')->label(__('Minimum'))->numeric()->integer(),
                TextInput::make('config.max')->label(__('Maximum'))->numeric()->integer(),
            ],
            'decimal' => [
                TextInput::make('config.precision')->label(__('Precision'))->numeric()->integer()->default(10),
                TextInput::make('config.scale')->label(__('Scale'))->numeric()->integer()->default(2),
                TextInput::make('config.min')->label(__('Minimum'))->numeric(),
                TextInput::make('config.max')->label(__('Maximum'))->numeric(),
            ],
            'boolean' => [
                Select::make('config.display_as')
                    ->label(__('Display as'))
                    ->options(['checkbox' => __('Checkbox'), 'toggle' => __('Toggle'), 'switch' => __('Switch')])
                    ->default('toggle'),
            ],
            'date', 'datetime', 'json', 'date_range', 'datetime_range' => [],
            'time' => [
                TextInput::make('config.step_minutes')->label(__('Step minutes'))->numeric()->integer(),
            ],
            'time_range' => [
                TextInput::make('config.step_minutes')->label(__('Step minutes'))->numeric()->integer(),
                Toggle::make('config.allow_overnight')->label(__('Allow overnight'))->default(false),
            ],
            'select', 'multi_select' => [
                Repeater::make('config.options')
                    ->label(__('Options'))
                    ->schema([
                        TextInput::make('value')->required(),
                        TextInput::make('label.en')->label(__('Label (English)'))->required(),
                        TextInput::make('label.da')->label(__('Label (Danish)')),
                    ])
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['value'] ?? null)
                    ->minItems(1),
            ],
            'relationship' => [
                Select::make('config.target_entity')
                    ->label(__('Target entity'))
                    ->options(fn (): array => collect(config('custom-fields.relationships.targets', []))
                        ->mapWithKeys(fn (array $target, string $key): array => [$key => $target['label'] ?? str($key)->replace('_', ' ')->title()->toString()])
                        ->all())
                    ->required(),
                Select::make('config.target_key_type')
                    ->label(__('Target key type'))
                    ->options(['uuid' => 'UUID', 'string' => 'String'])
                    ->default('uuid'),
                TextInput::make('config.display_field')->label(__('Display field'))->default('name')->required(),
            ],
            default => [],
        };
    }
}
