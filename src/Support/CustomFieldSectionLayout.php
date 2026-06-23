<?php

namespace ByJesper\LaravelCustomFieldsFilament\Support;

use Closure;
use Filament\Schemas\Components\Section;
use ByJesper\LaravelCustomFields\Models\CustomFieldDefinition;

/** @internal */
final class CustomFieldSectionLayout
{
    /**
     * @param  iterable<CustomFieldDefinition>  $definitions
     * @param  Closure(CustomFieldDefinition): mixed  $componentFor
     * @return array<int, Section>
     */
    public static function make(iterable $definitions, Closure $componentFor): array
    {
        $output = [];
        $currentGroup1 = null;
        $currentGroup2 = null;
        $group1Children = [];
        $group2Components = [];

        foreach ($definitions as $definition) {
            if ($definition->group_level_1 !== $currentGroup1) {
                if ($group2Components !== []) {
                    $group1Children[] = self::buildGroup2Section($currentGroup2, $group2Components);
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
                $group2Components = [];
            }

            if ($definition->group_level_2 !== $currentGroup2) {
                if ($group2Components !== []) {
                    $group1Children[] = self::buildGroup2Section($currentGroup2, $group2Components);
                }

                $currentGroup2 = $definition->group_level_2;
                $group2Components = [];
            }

            $group2Components[] = $componentFor($definition);
        }

        if ($group2Components !== []) {
            $group1Children[] = self::buildGroup2Section($currentGroup2, $group2Components);
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

    /** @param array<int, Section> $children */
    private static function buildGroup1Section(string $label, array $children): Section
    {
        return Section::make($label)->schema($children)->collapsible();
    }

    /** @param array<int, mixed> $components */
    private static function buildGroup2Section(?string $label, array $components): Section
    {
        return Section::make($label ?? __('General'))->schema($components)->collapsible();
    }
}
