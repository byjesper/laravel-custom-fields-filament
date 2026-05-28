<?php

namespace Yezper\LaravelCustomFieldsFilament\Components;

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
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Yezper\LaravelCustomFields\Models\CustomFieldDefinition;
use Yezper\LaravelCustomFields\Support\OptionLabelResolver;

class CustomFieldForm
{
    /** @return array<int, Section> */
    public static function make(string $entityType, ?Model $record = null): array
    {
        $definitionClass = config('custom-fields.models.definition');
        $definitions = $definitionClass::query()->active()
            ->forEntity($entityType)
            ->orderBy('group_level_1')
            ->orderBy('group_level_2')
            ->orderBy('sort_order')
            ->get();

        if ($definitions->isEmpty()) {
            return [];
        }

        $output = [];
        $currentGroup1 = null;
        $currentGroup2 = null;
        $group1Children = [];
        $group2Fields = [];

        foreach ($definitions as $definition) {
            $field = self::componentFor($definition);

            if ($record === null && $definition->default_value !== null && method_exists($field, 'default')) {
                $field->default($definition->default_value);
            }

            if ($definition->conditional_visibility !== null) {
                $field->visible(fn (Get $get): bool => self::evaluateVisibility($definition->conditional_visibility, $get));
            }

            if ($definition->group_level_1 !== $currentGroup1) {
                if ($group2Fields !== []) {
                    $group1Children[] = self::buildGroup2Section($currentGroup2, $group2Fields);
                }

                if ($group1Children !== []) {
                    if ($currentGroup1 !== null) {
                        $output[] = self::buildGroup1Section($currentGroup1, $group1Children);
                    } else {
                        array_push($output, ...$group1Children);
                    }
                }

                $currentGroup1 = $definition->group_level_1;
                $currentGroup2 = null;
                $group1Children = [];
                $group2Fields = [];
            }

            if ($definition->group_level_2 !== $currentGroup2) {
                if ($group2Fields !== []) {
                    $group1Children[] = self::buildGroup2Section($currentGroup2, $group2Fields);
                }

                $currentGroup2 = $definition->group_level_2;
                $group2Fields = [];
            }

            $group2Fields[] = $field;
        }

        if ($group2Fields !== []) {
            $group1Children[] = self::buildGroup2Section($currentGroup2, $group2Fields);
        }

        if ($group1Children !== []) {
            if ($currentGroup1 !== null) {
                $output[] = self::buildGroup1Section($currentGroup1, $group1Children);
            } else {
                array_push($output, ...$group1Children);
            }
        }

        return $output;
    }

    public static function componentFor(CustomFieldDefinition $definition): mixed
    {
        $required = $definition->getValidationRule('required', false);
        $name = "custom.{$definition->field_name}";

        if (in_array($definition->field_type, ['date_range', 'datetime_range', 'time_range'], true)) {
            return self::rangeField($definition, $name, $required);
        }

        $field = match ($definition->field_type) {
            'text' => Textarea::make($name)->rows(4),
            'integer' => TextInput::make($name)->numeric()->integer(),
            'decimal' => TextInput::make($name)->numeric()->step(pow(10, -((int) $definition->getConfigValue('scale', 2)))),
            'boolean' => Toggle::make($name),
            'date' => DatePicker::make($name),
            'datetime' => DateTimePicker::make($name),
            'time' => TimePicker::make($name),
            'select', 'enum' => Select::make($name)->options(app(OptionLabelResolver::class)->resolveForOptions($definition->getConfigValue('options', []))),
            'multi_select' => CheckboxList::make($name)->options(app(OptionLabelResolver::class)->resolveForOptions($definition->getConfigValue('options', []))),
            'relationship' => self::relationshipField($definition, $name),
            'json' => Textarea::make($name)
                ->rows(5)
                ->rules(['nullable', 'json'])
                ->dehydrateStateUsing(fn (?string $state): mixed => blank($state) ? null : json_decode($state, true))
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : json_encode($state, JSON_PRETTY_PRINT)),
            default => TextInput::make($name),
        };

        return $field
            ->label($definition->getLabel())
            ->required($required);
    }

    private static function relationshipField(CustomFieldDefinition $definition, string $name): Select
    {
        $targetEntity = $definition->getConfigValue('target_entity');
        $target = config("custom-fields.relationships.targets.{$targetEntity}", []);
        $modelClass = $target['model'] ?? null;
        $displayField = $definition->getConfigValue('display_field', $target['display_field'] ?? 'name');

        $field = Select::make($name)->searchable()->preload();

        if ($modelClass !== null) {
            $field->options($modelClass::query()->pluck($displayField, 'id')->all());
        }

        return $field;
    }

    private static function rangeField(CustomFieldDefinition $definition, string $name, bool $required): Fieldset
    {
        $fields = match ($definition->field_type) {
            'date_range' => [
                DatePicker::make("{$name}.start")->label(__('Start'))->required($required),
                DatePicker::make("{$name}.end")->label(__('End'))->required($required),
            ],
            'datetime_range' => [
                DateTimePicker::make("{$name}.start")->label(__('Start'))->required($required),
                DateTimePicker::make("{$name}.end")->label(__('End'))->required($required),
            ],
            'time_range' => [
                TimePicker::make("{$name}.start")->label(__('Start'))->required($required),
                TimePicker::make("{$name}.end")->label(__('End'))->required($required),
            ],
            default => [],
        };

        return Fieldset::make($definition->getLabel())->schema($fields);
    }

    /** @param array<int, Section> $children */
    private static function buildGroup1Section(string $label, array $children): Section
    {
        return Section::make($label)->schema($children)->collapsible();
    }

    /** @param array<int, mixed> $fields */
    private static function buildGroup2Section(?string $label, array $fields): Section
    {
        return Section::make($label ?? __('General'))->schema($fields)->collapsible();
    }

    /** @param array<string, mixed> $rules */
    private static function evaluateVisibility(array $rules, Get $get): bool
    {
        $operator = $rules['operator'] ?? 'and';
        $conditions = $rules['conditions'] ?? [];

        if ($conditions === []) {
            return true;
        }

        $results = [];

        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $op = $condition['op'];
            $value = $condition['value'] ?? null;
            $fieldValue = $get($field) ?? $get("custom.{$field}");

            $results[] = match ($op) {
                'eq' => $fieldValue === $value,
                'neq' => $fieldValue !== $value,
                'in' => is_array($value) && in_array($fieldValue, $value, true),
                'notIn' => is_array($value) && ! in_array($fieldValue, $value, true),
                'truthy' => (bool) $fieldValue,
                'falsy' => ! (bool) $fieldValue,
                default => false,
            };
        }

        return match ($operator) {
            'and' => ! in_array(false, $results, true),
            'or' => in_array(true, $results, true),
            default => false,
        };
    }
}
