<?php

namespace Yezper\LaravelCustomFieldsFilament\Components;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Yezper\LaravelCustomFields\Models\CustomFieldDefinition;
use Yezper\LaravelCustomFieldsFilament\Support\CustomFieldDisplayResolver;
use Yezper\LaravelCustomFieldsFilament\Support\CustomFieldSectionLayout;

class CustomFieldInfolist
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

        $resolver = app(CustomFieldDisplayResolver::class);

        return CustomFieldSectionLayout::make(
            $definitions,
            fn (CustomFieldDefinition $definition): TextEntry|IconEntry => self::entryFor($definition, $record, $resolver),
        );
    }

    private static function entryFor(CustomFieldDefinition $definition, ?Model $record, CustomFieldDisplayResolver $resolver): TextEntry|IconEntry
    {
        $value = $resolver->valueFor($record, $definition->field_name);
        $resolver->primeRelationshipLabels($definition, $record === null ? [] : [$record]);
        $name = "custom_field_{$definition->field_name}";

        if ($definition->field_type === 'boolean') {
            return IconEntry::make($name)
                ->label($definition->getLabel())
                ->state($value)
                ->boolean()
                ->placeholder('—');
        }

        $entry = TextEntry::make($name)
            ->label($definition->getLabel())
            ->state(match ($definition->field_type) {
                'date', 'datetime', 'time' => $value,
                default => $resolver->formatForInfolist($definition, $value),
            })
            ->placeholder('—');

        return match ($definition->field_type) {
            'date' => $entry->date(),
            'datetime' => $entry->dateTime(),
            'time' => $entry->time(),
            'integer' => $entry->numeric(0),
            'decimal' => $entry->numeric((int) $definition->getConfigValue('scale', 2)),
            'multi_select' => $entry->badge()->listWithLineBreaks(),
            'text', 'json' => $entry->columnSpanFull()->wrap(),
            default => $entry,
        };
    }
}
