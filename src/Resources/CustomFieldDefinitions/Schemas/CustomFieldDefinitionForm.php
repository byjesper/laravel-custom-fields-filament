<?php

namespace ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas;

use ByJesper\LaravelCustomFields\Services\CustomFieldTypeRegistry;
use ByJesper\LaravelCustomFieldsFilament\Rules\ConditionalVisibility;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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

            Section::make(__('Validation rules'))
                ->schema(fn (Get $get): array => self::validationRuleFields($get('field_type'))),

            Section::make(__('Default value'))->schema([
                Textarea::make('default_value')
                    ->label(__('Default JSON value'))
                    ->rows(2)
                    ->rules(['nullable', 'json'])
                    ->dehydrateStateUsing(fn (?string $state): mixed => blank($state) ? null : json_decode($state, true))
                    ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : (json_encode($state) ?: null)),
            ]),

            Section::make(__('Conditional visibility'))->schema([
                Textarea::make('conditional_visibility')
                    ->label(__('Visibility rule JSON'))
                    ->rows(6)
                    ->rules(['nullable', 'json'])
                    ->dehydrateStateUsing(fn (?string $state): mixed => blank($state) ? null : json_decode($state, true))
                    ->rule(new ConditionalVisibility)
                    ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : (json_encode($state, JSON_PRETTY_PRINT) ?: null)),
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
            'date', 'datetime', 'date_range', 'datetime_range' => [
                Callout::make(__('No type configuration'))
                    ->description(__('Use validation rules below for temporal bounds.'))
                    ->info(),
            ],
            'json' => [
                Callout::make(__('No type configuration'))
                    ->description(__('JSON fields do not need additional type configuration.'))
                    ->info(),
            ],
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

    /** @return array<int, mixed> */
    private static function validationRuleFields(?string $type): array
    {
        $fields = [
            Toggle::make('validation_rules.required')->label(__('Required'))->default(false),
        ];

        if (in_array($type, ['integer', 'decimal', 'string'], true)) {
            return [
                ...$fields,
                TextInput::make('validation_rules.min')
                    ->label(__('Minimum'))
                    ->numeric(),
                TextInput::make('validation_rules.max')
                    ->label(__('Maximum'))
                    ->numeric(),
            ];
        }

        if (is_string($type) && self::isTemporalType($type)) {
            return [
                ...$fields,
                ...self::temporalValidationRuleFields($type),
            ];
        }

        return $fields;
    }

    /** @return array<int, mixed> */
    private static function temporalValidationRuleFields(string $type): array
    {
        return [
            Select::make('validation_rules.min.type')
                ->label(__('Minimum mode'))
                ->options(self::temporalBoundTypeOptions($type))
                ->placeholder(__('No minimum'))
                ->live(),
            self::fixedTemporalField('min', $type),
            ...self::relativeTemporalFields('min', $type),

            Select::make('validation_rules.max.type')
                ->label(__('Maximum mode'))
                ->options(self::temporalBoundTypeOptions($type))
                ->placeholder(__('No maximum'))
                ->live(),
            self::fixedTemporalField('max', $type),
            ...self::relativeTemporalFields('max', $type),
        ];
    }

    /** @return array<string, string> */
    private static function temporalBoundTypeOptions(string $type): array
    {
        $options = ['fixed' => __('Fixed value')];

        if (self::supportsRelativeBounds($type)) {
            $options['relative'] = __('Relative value');
        }

        return $options;
    }

    private static function fixedTemporalField(string $bound, string $type): DatePicker|DateTimePicker|TimePicker
    {
        $field = match (self::temporalBaseType($type)) {
            'date' => DatePicker::make("validation_rules.{$bound}.value"),
            'datetime' => DateTimePicker::make("validation_rules.{$bound}.value")->seconds(false),
            'time' => TimePicker::make("validation_rules.{$bound}.value")->seconds(false),
            default => throw new \InvalidArgumentException("Unsupported temporal type [{$type}]."),
        };

        return $field
            ->label($bound === 'min' ? __('Minimum value') : __('Maximum value'))
            ->visible(fn (Get $get): bool => $get("validation_rules.{$bound}.type") === 'fixed');
    }

    /** @return array<int, mixed> */
    private static function relativeTemporalFields(string $bound, string $type): array
    {
        if (! self::supportsRelativeBounds($type)) {
            return [];
        }

        return [
            TextInput::make("validation_rules.{$bound}.offset")
                ->label($bound === 'min' ? __('Minimum offset') : __('Maximum offset'))
                ->numeric()
                ->integer()
                ->default(0)
                ->visible(fn (Get $get): bool => $get("validation_rules.{$bound}.type") === 'relative'),
            Select::make("validation_rules.{$bound}.unit")
                ->label($bound === 'min' ? __('Minimum unit') : __('Maximum unit'))
                ->options(self::relativeUnitOptions())
                ->default('days')
                ->visible(fn (Get $get): bool => $get("validation_rules.{$bound}.type") === 'relative'),
        ];
    }

    /** @return array<string, string> */
    private static function relativeUnitOptions(): array
    {
        return [
            'minutes' => __('Minutes'),
            'hours' => __('Hours'),
            'days' => __('Days'),
            'weeks' => __('Weeks'),
            'months' => __('Months'),
            'years' => __('Years'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareFormDataForFill(array $data): array
    {
        $fieldType = $data['field_type'] ?? null;

        if (! is_string($fieldType) || ! self::isTemporalType($fieldType)) {
            return $data;
        }

        $validationRules = $data['validation_rules'] ?? [];

        if (! is_array($validationRules)) {
            return $data;
        }

        foreach (['min', 'max'] as $bound) {
            if (isset($validationRules[$bound]) && is_string($validationRules[$bound])) {
                $validationRules[$bound] = [
                    'type' => 'fixed',
                    'value' => $validationRules[$bound],
                ];
            }
        }

        $data['validation_rules'] = $validationRules;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        $fieldType = $data['field_type'] ?? null;

        if (! is_string($fieldType) || ! self::isTemporalType($fieldType)) {
            return $data;
        }

        $validationRules = $data['validation_rules'] ?? [];

        if (! is_array($validationRules)) {
            $validationRules = [];
        }

        foreach (['min', 'max'] as $bound) {
            $normalized = self::normalizeTemporalBound($validationRules[$bound] ?? null, $fieldType);

            if ($normalized === null) {
                unset($validationRules[$bound]);

                continue;
            }

            $validationRules[$bound] = $normalized;
        }

        $data['validation_rules'] = $validationRules;

        return $data;
    }

    /** @return array<string, mixed>|null */
    private static function normalizeTemporalBound(mixed $rule, string $fieldType): ?array
    {
        if (! is_array($rule)) {
            return null;
        }

        $type = $rule['type'] ?? null;

        if ($type === 'fixed') {
            $value = $rule['value'] ?? null;

            return blank($value) ? null : [
                'type' => 'fixed',
                'value' => $value,
            ];
        }

        if ($type !== 'relative' || ! self::supportsRelativeBounds($fieldType)) {
            return null;
        }

        $offset = $rule['offset'] ?? null;
        $unit = $rule['unit'] ?? null;

        if ((is_string($offset) && preg_match('/^-?\d+$/', $offset) === 1) || is_int($offset)) {
            $offset = (int) $offset;
        }

        if (! is_int($offset) || ! is_string($unit) || $unit === '') {
            return null;
        }

        return [
            'type' => 'relative',
            'anchor' => self::relativeAnchor($fieldType),
            'offset' => $offset,
            'unit' => $unit,
        ];
    }

    private static function isTemporalType(string $type): bool
    {
        return in_array($type, ['date', 'datetime', 'time', 'date_range', 'datetime_range', 'time_range'], true);
    }

    private static function supportsRelativeBounds(string $type): bool
    {
        return in_array($type, ['date', 'datetime', 'date_range', 'datetime_range'], true);
    }

    private static function relativeAnchor(string $type): string
    {
        return in_array($type, ['date', 'date_range'], true) ? 'today' : 'now';
    }

    private static function temporalBaseType(string $type): string
    {
        return match ($type) {
            'date', 'date_range' => 'date',
            'datetime', 'datetime_range' => 'datetime',
            'time', 'time_range' => 'time',
            default => throw new \InvalidArgumentException("Unsupported temporal type [{$type}]."),
        };
    }
}
