<?php

namespace ByJesper\LaravelCustomFieldsFilament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;

class CustomFieldsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'custom-fields';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            CustomFieldDefinitionResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
