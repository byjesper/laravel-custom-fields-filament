<?php

namespace ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use ByJesper\LaravelCustomFields\Models\CustomFieldDefinition;
use ByJesper\LaravelCustomFields\Services\CustomFieldTypeRegistry;

class CustomFieldDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('field_name')->searchable()->sortable(),
                TextColumn::make('field_label')->state(fn (CustomFieldDefinition $record): string => $record->getLabel()),
                TextColumn::make('entity_type')->badge(),
                TextColumn::make('field_type')->badge(),
                TextColumn::make('group_level_1')->sortable(),
                TextColumn::make('group_level_2')->sortable(),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('entity_type')->options(fn (): array => collect(config('custom-fields.entities.enabled', []))
                    ->mapWithKeys(fn (string $entity): array => [$entity => str($entity)->replace('_', ' ')->title()->toString()])
                    ->all()),
                SelectFilter::make('field_type')->options(fn (): array => app(CustomFieldTypeRegistry::class)->options()),
                TernaryFilter::make('is_active')->default(true),
                SelectFilter::make('group_level_1')
                    ->options(fn (): array => config('custom-fields.models.definition')::query()
                        ->distinct()
                        ->pluck('group_level_1', 'group_level_1')
                        ->filter()
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (?CustomFieldDefinition $record): string => $record?->is_active ? __('Deactivate') : __('Activate'))
                    ->color(fn (?CustomFieldDefinition $record): string => $record?->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (?CustomFieldDefinition $record): void {
                        $record?->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title(__('Custom field updated'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (?CustomFieldDefinition $record): bool => $record !== null),
            ]);
    }
}
