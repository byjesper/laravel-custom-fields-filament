<?php

namespace ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages;

use Filament\Resources\Pages\EditRecord;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas\CustomFieldDefinitionForm;

class EditCustomFieldDefinition extends EditRecord
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }

    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CustomFieldDefinitionForm::prepareFormDataForFill($data);
    }

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CustomFieldDefinitionForm::normalizeFormData($data);
    }
}
