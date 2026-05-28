<?php

namespace Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Yezper\LaravelCustomFields\Services\ContextResolver;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages\CreateCustomFieldDefinition;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages\EditCustomFieldDefinition;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages\ListCustomFieldDefinitions;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas\CustomFieldDefinitionForm;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Tables\CustomFieldDefinitionsTable;

class CustomFieldDefinitionResource extends Resource
{
    protected static ?string $model = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Custom Fields';

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function getModel(): string
    {
        return config('custom-fields.models.definition');
    }

    #[\Override]
    public static function getNavigationGroup(): string
    {
        return __('Settings');
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return CustomFieldDefinitionForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return CustomFieldDefinitionsTable::configure($table);
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        foreach (app(ContextResolver::class)->current()->attributes() as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListCustomFieldDefinitions::route('/'),
            'create' => CreateCustomFieldDefinition::route('/create'),
            'edit' => EditCustomFieldDefinition::route('/{record}/edit'),
        ];
    }
}
