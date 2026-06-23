<?php

namespace ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages;

use Filament\Resources\Pages\CreateRecord;
use ByJesper\LaravelCustomFields\Services\ContextResolver;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Schemas\CustomFieldDefinitionForm;

class CreateCustomFieldDefinition extends CreateRecord
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge(
            app(ContextResolver::class)->current()->attributes(),
            CustomFieldDefinitionForm::normalizeFormData($data),
        );
    }
}
