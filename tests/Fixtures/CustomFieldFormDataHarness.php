<?php

namespace ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures;

use ByJesper\LaravelCustomFieldsFilament\Concerns\HandlesCustomFieldFormData;

class CustomFieldFormDataHarness
{
    use HandlesCustomFieldFormData;

    public static function getModel(): string
    {
        return Contact::class;
    }

    public function build(array $customData): array
    {
        return $this->buildCustomFieldValues($customData);
    }

    public function validateData(array $customData): void
    {
        $this->validateCustomFieldsInData($customData);
    }
}
