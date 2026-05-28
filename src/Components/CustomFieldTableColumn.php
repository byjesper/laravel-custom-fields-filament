<?php

namespace Yezper\LaravelCustomFieldsFilament\Components;

use Carbon\Carbon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Yezper\LaravelCustomFields\Models\CustomFieldDefinition;
use Yezper\LaravelCustomFields\Support\OptionLabelResolver;

class CustomFieldTableColumn
{
    /** @var array<string, Model|null> */
    protected static array $relationshipCache = [];

    /** @return array<int, TextColumn|IconColumn> */
    public static function make(string $entityType): array
    {
        $definitionClass = config('custom-fields.models.definition');
        $definitions = $definitionClass::query()->active()
            ->forEntity($entityType)
            ->orderBy('group_level_1')
            ->orderBy('group_level_2')
            ->orderBy('sort_order')
            ->get();

        return $definitions->map(fn (CustomFieldDefinition $definition): TextColumn|IconColumn => $definition->field_type === 'boolean'
            ? self::buildBooleanColumn($definition)
            : self::buildTextColumn($definition))->all();
    }

    private static function buildTextColumn(CustomFieldDefinition $definition): TextColumn
    {
        return TextColumn::make("custom_field_{$definition->field_name}")
            ->label($definition->getLabel())
            ->state(fn ($record): mixed => self::resolveValue($definition, $record->getCustomFieldValue($definition->field_name)))
            ->toggleable()
            ->sortable(false)
            ->searchable(false);
    }

    private static function buildBooleanColumn(CustomFieldDefinition $definition): IconColumn
    {
        return IconColumn::make("custom_field_{$definition->field_name}")
            ->label($definition->getLabel())
            ->state(fn ($record): mixed => $record->getCustomFieldValue($definition->field_name))
            ->boolean()
            ->toggleable();
    }

    private static function resolveValue(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($definition->field_type) {
            'select', 'enum', 'multi_select' => app(OptionLabelResolver::class)->resolveForDisplay($definition->getConfigValue('options', []), $value),
            'relationship' => self::resolveRelationshipLabel($definition, $value),
            'date' => Carbon::parse($value)->format('Y-m-d'),
            'datetime' => Carbon::parse($value)->format('Y-m-d H:i'),
            'date_range' => self::formatRange($value, fn (mixed $date): string => Carbon::parse($date)->format('Y-m-d')),
            'datetime_range' => self::formatRange($value, fn (mixed $date): string => Carbon::parse($date)->format('Y-m-d H:i')),
            'time_range' => self::formatRange($value, fn (mixed $time): string => (string) $time),
            default => is_array($value) ? json_encode($value) : $value,
        };
    }

    private static function formatRange(mixed $value, callable $formatter): ?string
    {
        if (! is_array($value) || ! isset($value['start'], $value['end'])) {
            return null;
        }

        return $formatter($value['start']).' - '.$formatter($value['end']);
    }

    private static function resolveRelationshipLabel(CustomFieldDefinition $definition, mixed $value): ?string
    {
        $targetEntity = $definition->getConfigValue('target_entity');
        $target = config("custom-fields.relationships.targets.{$targetEntity}", []);
        $modelClass = $target['model'] ?? null;
        $displayField = $definition->getConfigValue('display_field', $target['display_field'] ?? 'name');

        if ($modelClass === null || $value === null) {
            return $value;
        }

        $cacheKey = $modelClass.'::'.$value;
        if (! array_key_exists($cacheKey, static::$relationshipCache)) {
            static::$relationshipCache[$cacheKey] = $modelClass::find($value);
        }

        $cached = static::$relationshipCache[$cacheKey];

        return $cached === null ? $value : ($cached->{$displayField} ?? $value);
    }
}
