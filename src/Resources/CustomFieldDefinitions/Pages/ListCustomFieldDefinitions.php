<?php

namespace ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages;

use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomFieldDefinitions extends ListRecords
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
