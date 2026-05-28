<?php

namespace Yezper\LaravelCustomFieldsFilament\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Yezper\LaravelCustomFields\Models\CustomFieldDefinition;
use Yezper\LaravelCustomFields\Services\CustomFieldQueryBuilder;
use Yezper\LaravelCustomFields\Support\OptionLabelResolver;

class CustomFieldTableFilter
{
    /** @return array<int, Filter|SelectFilter|TernaryFilter> */
    public static function make(string $entityType): array
    {
        $definitionClass = config('custom-fields.models.definition');

        return $definitionClass::query()->active()
            ->forEntity($entityType)
            ->orderBy('group_level_1')
            ->orderBy('group_level_2')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CustomFieldDefinition $definition): mixed => match ($definition->field_type) {
                'boolean' => self::buildBooleanFilter($definition),
                'select', 'enum' => self::buildSelectFilter($definition),
                'multi_select' => self::buildMultiSelectFilter($definition),
                'date', 'datetime' => self::buildDateFilter($definition),
                'integer', 'decimal' => self::buildNumericFilter($definition),
                default => self::buildStringFilter($definition),
            })
            ->all();
    }

    private static function buildStringFilter(CustomFieldDefinition $definition): Filter
    {
        return Filter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->schema([TextInput::make('value')->label($definition->getLabel())])
            ->query(fn (Builder $query, array $data) => blank($data['value'] ?? null)
                ? null
                : app(CustomFieldQueryBuilder::class)->applyFilter($query, $definition->entity_type, $definition->field_name, 'like', $data['value']));
    }

    private static function buildNumericFilter(CustomFieldDefinition $definition): Filter
    {
        return Filter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->schema([TextInput::make('value')->label($definition->getLabel())->numeric()])
            ->query(fn (Builder $query, array $data) => blank($data['value'] ?? null)
                ? null
                : app(CustomFieldQueryBuilder::class)->applyFilter($query, $definition->entity_type, $definition->field_name, '=', $data['value']));
    }

    private static function buildBooleanFilter(CustomFieldDefinition $definition): TernaryFilter
    {
        return TernaryFilter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->queries(
                true: fn (Builder $query) => app(CustomFieldQueryBuilder::class)->applyFilter($query, $definition->entity_type, $definition->field_name, '=', true),
                false: fn (Builder $query) => app(CustomFieldQueryBuilder::class)->applyFilter($query, $definition->entity_type, $definition->field_name, '=', false),
                blank: fn (Builder $query) => $query,
            );
    }

    private static function buildSelectFilter(CustomFieldDefinition $definition): SelectFilter
    {
        return SelectFilter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->options(app(OptionLabelResolver::class)->resolveForOptions($definition->getConfigValue('options', [])))
            ->query(fn (Builder $query, array $data) => blank($data['value'] ?? null)
                ? null
                : app(CustomFieldQueryBuilder::class)->applyFilter($query, $definition->entity_type, $definition->field_name, '=', $data['value']));
    }

    private static function buildMultiSelectFilter(CustomFieldDefinition $definition): SelectFilter
    {
        return SelectFilter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->options(app(OptionLabelResolver::class)->resolveForOptions($definition->getConfigValue('options', [])))
            ->multiple()
            ->query(function (Builder $query, array $data) use ($definition): void {
                $value = $data['value'] ?? $data['values'] ?? null;

                if (filled($value)) {
                    app(CustomFieldQueryBuilder::class)->applyFilter($query, $definition->entity_type, $definition->field_name, 'contains', $value);
                }
            });
    }

    private static function buildDateFilter(CustomFieldDefinition $definition): Filter
    {
        return Filter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->schema([
                DatePicker::make('from')->label("{$definition->getLabel()} from"),
                DatePicker::make('until')->label("{$definition->getLabel()} until"),
            ])
            ->query(function (Builder $query, array $data) use ($definition): void {
                $from = $data['from'] ?? null;
                $until = $data['until'] ?? null;

                if (blank($from) && blank($until)) {
                    return;
                }

                app(CustomFieldQueryBuilder::class)->applyFilter(
                    $query,
                    $definition->entity_type,
                    $definition->field_name,
                    blank($from) ? '<=' : (blank($until) ? '>=' : 'range'),
                    blank($from) ? $until : (blank($until) ? $from : [$from, $until]),
                );
            });
    }
}
