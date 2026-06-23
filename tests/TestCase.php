<?php

namespace ByJesper\LaravelCustomFieldsFilament\Tests;

use ByJesper\LaravelCustomFields\CustomFieldsServiceProvider;
use ByJesper\LaravelCustomFieldsFilament\CustomFieldsFilamentServiceProvider;
use ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures\Contact;
use ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            CustomFieldsServiceProvider::class,
            CustomFieldsFilamentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('custom-fields.entities.enabled', ['contact']);
        $app['config']->set('custom-fields.entities.models.contact', Contact::class);
        $app['config']->set('custom-fields.relationships.targets.organization', [
            'label' => 'Organizations',
            'model' => Organization::class,
            'display_field' => 'name',
        ]);
    }

    public function resetSqliteDatabase(): void
    {
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Model::clearBootedModels();
    }

    public function createCustomFieldTables(): void
    {
        Schema::dropIfExists('custom_field_index_values');
        Schema::dropIfExists('custom_field_definitions');

        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('team_id')->nullable();
            $table->string('entity_type');
            $table->string('field_name');
            $table->json('field_label');
            $table->string('field_type');
            $table->json('config')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('conditional_visibility')->nullable();
            $table->json('default_value')->nullable();
            $table->string('group_level_1')->nullable();
            $table->string('group_level_2')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('custom_field_index_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('team_id')->nullable();
            $table->string('definition_id');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->string('value_string')->nullable();
            $table->text('value_text')->nullable();
            $table->integer('value_integer')->nullable();
            $table->decimal('value_decimal')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->time('value_time')->nullable();
            $table->string('value_uuid')->nullable();
            $table->json('value_json')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();
            $table->timestamps();
        });
    }

    public function createEntityTables(): void
    {
        Schema::dropIfExists('cf_filament_contacts');
        Schema::dropIfExists('cf_filament_organizations');

        Schema::create('cf_filament_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->json('custom_field_values')->nullable();
            $table->timestamps();
        });

        Schema::create('cf_filament_organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });
    }
}
