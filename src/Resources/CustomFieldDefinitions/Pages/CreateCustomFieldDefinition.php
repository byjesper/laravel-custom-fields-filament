<?php

namespace Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\Pages;

use Filament\Resources\Pages\CreateRecord;
use Yezper\LaravelCustomFields\Services\ContextResolver;
use Yezper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;

class CreateCustomFieldDefinition extends CreateRecord
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge(app(ContextResolver::class)->current()->attributes(), $data);
    }
}
