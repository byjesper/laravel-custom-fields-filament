<?php

namespace ByJesper\LaravelCustomFieldsFilament;

use ByJesper\LaravelCustomFieldsFilament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

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
