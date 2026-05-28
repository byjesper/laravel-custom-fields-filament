<?php

namespace Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;

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
