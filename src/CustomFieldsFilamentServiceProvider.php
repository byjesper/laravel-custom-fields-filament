<?php

namespace ByJesper\LaravelCustomFieldsFilament;

use ByJesper\LaravelCustomFieldsFilament\Support\CustomFieldDisplayResolver;
use Illuminate\Support\ServiceProvider;

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
