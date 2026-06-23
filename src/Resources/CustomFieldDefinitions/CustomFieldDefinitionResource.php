<?php

namespace ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions;

use BackedEnum;
use ByJesper\LaravelCustomFields\Services\ContextResolver;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages\CreateCustomFieldDefinition;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages\EditCustomFieldDefinition;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages\ListCustomFieldDefinitions;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas\CustomFieldDefinitionForm;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Tables\CustomFieldDefinitionsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
