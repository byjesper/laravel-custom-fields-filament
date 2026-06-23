<?php

namespace ByJesper\LaravelCustomFieldsFilament\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use ByJesper\LaravelCustomFields\Models\CustomFieldDefinition;
use ByJesper\LaravelCustomFields\Services\CustomFieldQueryBuilder;
use ByJesper\LaravelCustomFields\Support\OptionLabelResolver;

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
                'date_range', 'datetime_range', 'time_range' => self::buildRangeFilter($definition),
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

    private static function buildRangeFilter(CustomFieldDefinition $definition): Filter
    {
        return Filter::make("custom_{$definition->field_name}")
            ->label($definition->getLabel())
            ->schema(self::rangeFilterSchema($definition))
            ->query(function (Builder $query, array $data) use ($definition): void {
                $from = $data['from'] ?? null;
                $until = $data['until'] ?? null;

                if (blank($from) && blank($until)) {
                    return;
                }

                if (blank($from) || blank($until)) {
                    app(CustomFieldQueryBuilder::class)->applyFilter(
                        $query,
                        $definition->entity_type,
                        $definition->field_name,
                        'range_contains',
                        blank($from) ? $until : $from,
                    );

                    return;
                }

                app(CustomFieldQueryBuilder::class)->applyFilter(
                    $query,
                    $definition->entity_type,
                    $definition->field_name,
                    'range_overlaps',
                    ['start' => $from, 'end' => $until],
                );
            });
    }

    /** @return array<int, mixed> */
    private static function rangeFilterSchema(CustomFieldDefinition $definition): array
    {
        return match ($definition->field_type) {
            'datetime_range' => [
                DateTimePicker::make('from')->label("{$definition->getLabel()} from"),
                DateTimePicker::make('until')->label("{$definition->getLabel()} until"),
            ],
            'time_range' => [
                TimePicker::make('from')->label("{$definition->getLabel()} from"),
                TimePicker::make('until')->label("{$definition->getLabel()} until"),
            ],
            default => [
                DatePicker::make('from')->label("{$definition->getLabel()} from"),
                DatePicker::make('until')->label("{$definition->getLabel()} until"),
            ],
        };
    }
}
