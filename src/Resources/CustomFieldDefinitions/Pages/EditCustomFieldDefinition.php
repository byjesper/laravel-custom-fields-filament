<?php

namespace Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages;

use Filament\Resources\Pages\EditRecord;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;

class EditCustomFieldDefinition extends EditRecord
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
