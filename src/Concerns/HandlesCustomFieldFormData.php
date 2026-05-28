<?php

namespace Yezper\LaravelCustomFieldsFilament\Concerns;

use Illuminate\Validation\ValidationException;

trait HandlesCustomFieldFormData
{
    protected function buildCustomFieldValues(array $customData): array
    {
        $result = [];

        foreach ($customData as $field => $value) {
            if ($value !== null) {
                $result[$field] = ['value' => $value];
            }
        }

        return $result;
    }

    protected function validateCustomFieldsInData(array $customData): void
    {
        $record = new (static::getModel());

        try {
            $record->validateCustomFields($customData);
        } catch (ValidationException $e) {
            $messages = [];

            foreach ($e->errors() as $key => $errors) {
                $messages[str_starts_with((string) $key, 'custom.') ? "data.{$key}" : $key] = $errors;
            }

            throw ValidationException::withMessages($messages);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'data.custom' => [$e->getMessage()],
            ]);
        }
    }
}
