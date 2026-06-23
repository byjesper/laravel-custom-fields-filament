<?php

namespace ByJesper\LaravelCustomFieldsFilament\Support;

use ByJesper\LaravelCustomFields\Models\CustomFieldDefinition;
use ByJesper\LaravelCustomFields\Support\OptionLabelResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @internal */
final class CustomFieldDisplayResolver
{
    /** @var array<string, array<string, mixed>> */
    private array $relationshipLabels = [];

    /** @var array<string, array<string, true>> */
    private array $primedRelationshipIds = [];

    public function valueFor(?Model $record, string $fieldName): mixed
    {
        if ($record === null) {
            return null;
        }

        if (! method_exists($record, 'getCustomFieldValue')) {
            throw new LogicException('Custom field display records must implement getCustomFieldValue().');
        }

        return $record->getCustomFieldValue($fieldName);
    }

    /** @param iterable<mixed> $records */
    public function primeRelationshipLabels(CustomFieldDefinition $definition, iterable $records): void
    {
        $target = $this->relationshipTargetFor($definition);

        if ($target === null) {
            return;
        }

        [$modelClass, $displayField] = $target;
        $cacheKey = $this->relationshipCacheKey($modelClass, $displayField);
        $ids = [];

        foreach ($records as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            $value = $this->valueFor($record, $definition->field_name);

            if ($value !== null && $value !== '') {
                $ids[(string) $value] = $value;
            }
        }

        $missingIds = array_filter(
            $ids,
            fn (mixed $id): bool => ! isset($this->primedRelationshipIds[$cacheKey][(string) $id]),
        );

        if ($missingIds === []) {
            return;
        }

        // Hydrate full models rather than pluck()ing the single display column: when the display
        // field is an accessor that derives from other columns (e.g. display_name from first/last
        // name), pluck() mutates a partial model with only that column loaded and the accessor's
        // fallback collapses to an empty value. Loading the whole record keeps it a single query
        // while letting accessors see every attribute they depend on.
        $labels = $modelClass::query()
            ->whereKey(array_values($missingIds))
            ->get()
            ->mapWithKeys(fn (Model $related): array => [
                (string) $related->getKey() => $related->{$displayField},
            ])
            ->all();

        foreach ($missingIds as $id) {
            $normalizedId = (string) $id;
            $this->primedRelationshipIds[$cacheKey][$normalizedId] = true;
            $this->relationshipLabels[$cacheKey][$normalizedId] = $labels[$normalizedId] ?? $id;
        }
    }

    public function formatForTable(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($definition->field_type) {
            'select', 'enum', 'multi_select' => app(OptionLabelResolver::class)->resolveForDisplay($definition->getConfigValue('options', []), $value),
            'relationship' => $this->relationshipLabel($definition, $value),
            'date' => Carbon::parse($value)->format('Y-m-d'),
            'datetime' => Carbon::parse($value)->format('Y-m-d H:i'),
            'date_range' => $this->formatRange($value, fn (mixed $date): string => Carbon::parse($date)->format('Y-m-d')),
            'datetime_range' => $this->formatRange($value, fn (mixed $date): string => Carbon::parse($date)->format('Y-m-d H:i')),
            'time_range' => $this->formatRange($value, fn (mixed $time): string => (string) $time),
            default => is_array($value) ? json_encode($value) : $value,
        };
    }

    public function formatForInfolist(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($definition->field_type === 'multi_select' && is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => app(OptionLabelResolver::class)->resolveForDisplay($definition->getConfigValue('options', []), $item),
                $value,
            );
        }

        if ($definition->field_type === 'json' && is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT) ?: null;
        }

        return $this->formatForTable($definition, $value);
    }

    private function relationshipLabel(CustomFieldDefinition $definition, mixed $value): mixed
    {
        $target = $this->relationshipTargetFor($definition);

        if ($target === null || $value === null) {
            return $value;
        }

        [$modelClass, $displayField] = $target;
        $cacheKey = $this->relationshipCacheKey($modelClass, $displayField);

        return $this->relationshipLabels[$cacheKey][(string) $value] ?? $value;
    }

    /** @return array{0: class-string<Model>, 1: string}|null */
    private function relationshipTargetFor(CustomFieldDefinition $definition): ?array
    {
        $targetEntity = $definition->getConfigValue('target_entity');
        $target = config("custom-fields.relationships.targets.{$targetEntity}", []);
        $modelClass = $target['model'] ?? null;

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            return null;
        }

        return [$modelClass, $definition->getConfigValue('display_field', $target['display_field'] ?? 'name')];
    }

    private function relationshipCacheKey(string $modelClass, string $displayField): string
    {
        return $modelClass.'::'.$displayField;
    }

    private function formatRange(mixed $value, callable $formatter): ?string
    {
        if (! is_array($value) || ! isset($value['start'], $value['end'])) {
            return null;
        }

        return $formatter($value['start']).' - '.$formatter($value['end']);
    }
}
