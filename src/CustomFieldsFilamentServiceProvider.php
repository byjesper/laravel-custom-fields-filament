<?php

namespace ByJesper\LaravelCustomFieldsFilament;

use Illuminate\Support\ServiceProvider;
use ByJesper\LaravelCustomFieldsFilament\Support\CustomFieldDisplayResolver;

class CustomFieldsFilamentServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->scoped(CustomFieldDisplayResolver::class);
    }

    public function boot(): void
    {
        //
    }
}
