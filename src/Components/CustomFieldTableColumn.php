<?php

namespace ByJesper\LaravelCustomFieldsFilament\Components;

use ByJesper\LaravelCustomFields\Models\CustomFieldDefinition;
use ByJesper\LaravelCustomFieldsFilament\Support\CustomFieldDisplayResolver;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

class CustomFieldTableColumn
{
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
        $column = TextColumn::make("custom_field_{$definition->field_name}")
            ->label($definition->getLabel())
            ->toggleable()
            ->sortable(false)
            ->searchable(false);

        return $definition->field_type === 'relationship'
            ? $column->state(function (Model $record, Table $table) use ($definition): mixed {
                $resolver = app(CustomFieldDisplayResolver::class);
                $records = $table->getRecords();
                $resolver->primeRelationshipLabels(
                    $definition,
                    ($records instanceof Paginator || $records instanceof CursorPaginator) ? $records->items() : $records,
                );

                return $resolver->formatForTable($definition, $resolver->valueFor($record, $definition->field_name));
            })
            : $column->state(fn (Model $record): mixed => app(CustomFieldDisplayResolver::class)->formatForTable(
                $definition,
                app(CustomFieldDisplayResolver::class)->valueFor($record, $definition->field_name),
            ));
    }

    private static function buildBooleanColumn(CustomFieldDefinition $definition): IconColumn
    {
        return IconColumn::make("custom_field_{$definition->field_name}")
            ->label($definition->getLabel())
            ->state(fn (Model $record): mixed => app(CustomFieldDisplayResolver::class)->valueFor($record, $definition->field_name))
            ->boolean()
            ->toggleable();
    }
}
