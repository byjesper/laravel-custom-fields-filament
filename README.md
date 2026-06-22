# Laravel Custom Fields — Filament

Filament v5 admin UI for [`yezper/laravel-custom-fields`](https://packagist.org/packages/yezper/laravel-custom-fields).

Plug-and-play resources, form components, and table columns for managing
custom field definitions and editing per-record values inside any Filament
panel.

## What's included

- **`CustomFieldDefinitionResource`** — full CRUD for definitions
  (list / create / edit) with grouping, validation rules, conditional
  visibility, and per-type config.
- **`CustomFieldForm::make($entityType, ?$record)`** — builds the form schema
  for an entity, automatically grouped into two-level collapsible sections
  driven by `group_level_1` / `group_level_2`.
- **`CustomFieldInfolist::make($entityType, ?$record)`** — builds the matching
  read-only infolist schema, with type-aware display values and the same
  grouping as the form.
- **`CustomFieldTableColumn::make($entityType)`** — returns toggleable table
  columns for every active custom field, with proper formatting for selects,
  relationships, dates, ranges, and booleans.
- **`CustomFieldTableFilter`** — table filter components backed by
  `CustomFieldQueryBuilder`, including date/time range filters.
- **`HandlesCustomFieldFormData`** trait — shapes raw form data into the
  `['value' => …]` envelope and validates with the host model.
- **`ConditionalVisibility` rule** — server-side enforcement of the same
  rule tree the form uses for visibility.

## Requirements

- PHP **8.4+**
- Laravel **13.x**
- Filament **5.x**
- `yezper/laravel-custom-fields` **^1.1**

## Installation

```bash
composer require yezper/laravel-custom-fields-filament
```

Make sure the core package is installed and migrated (see its
[README](https://github.com/yezper/laravel-custom-fields/blob/main/README.md)).

### Register the plugin

In your panel provider:

```php
use Filament\Panel;
use Yezper\LaravelCustomFieldsFilament\CustomFieldsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            CustomFieldsPlugin::make(),
        ]);
}
```

This registers `CustomFieldDefinitionResource` in the panel — admins can now
manage definitions at `/admin/custom-field-definitions`.

## Editing custom fields on your own resources

Inside any Filament resource form, drop in the generated schema for that
entity:

```php
use Filament\Schemas\Schema;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldForm;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ...your native form fields...

        ...CustomFieldForm::make('contact', $schema->getRecord()),
    ]);
}
```

Fields are namespaced under `custom.*` (e.g. `custom.lifetime_value`) so they
don't collide with native model attributes. Use the
`HandlesCustomFieldFormData` trait on your `Create`/`Edit` pages to shape and
validate the payload before saving:

```php
use Yezper\LaravelCustomFieldsFilament\Concerns\HandlesCustomFieldFormData;

class EditContact extends EditRecord
{
    use HandlesCustomFieldFormData;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $custom = $data['custom'] ?? [];
        unset($data['custom']);

        $this->validateCustomFieldsInData($custom);
        $data['custom_field_values'] = $this->buildCustomFieldValues($custom);

        return $data;
    }
}
```

The `custom_field_values` column is observed by the core package, which
mirrors changes into `custom_field_index_values` on save.

## Displaying custom fields in infolists

Use the read-only counterpart on a View/detail page. Pass the displayed model
so the component can read values through the core package's
`getCustomFieldValue()` accessor:

```php
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldInfolist;

Tab::make(__('fields.custom_fields'))
    ->schema(fn (?Model $record) => CustomFieldInfolist::make('contact', $record));
```

The infolist is display-only. It does not apply form defaults or conditional
visibility rules, so a field hidden by a form condition remains visible on the
detail page in this release.

## Showing custom fields in tables

```php
use Filament\Tables\Table;
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldTableColumn;

public static function table(Table $table): Table
{
    return $table->columns([
        // ...your native columns...

        ...CustomFieldTableColumn::make('contact'),
    ]);
}
```

The helper picks a sensible column type per field type — `IconColumn` for
booleans, `TextColumn` for everything else — and resolves select labels and
relationship display fields automatically. All custom-field columns are
`toggleable()` so they can be hidden from the column picker by default if
the list is long.

## Filtering by custom fields

```php
use Yezper\LaravelCustomFieldsFilament\Components\CustomFieldTableFilter;

$table->filters([
    ...CustomFieldTableFilter::make('contact'),
]);
```

## Field-type → Filament component mapping

| Field type       | Component            |
| ---------------- | -------------------- |
| `string`         | `TextInput`          |
| `text`           | `Textarea` (4 rows)  |
| `integer`        | `TextInput` numeric  |
| `decimal`        | `TextInput` numeric, step from `config.scale` |
| `boolean`        | `Toggle`             |
| `date`           | `DatePicker`         |
| `datetime`       | `DateTimePicker`     |
| `time`           | `TimePicker`         |
| `date_range`     | `Fieldset` with two `DatePicker` fields |
| `datetime_range` | `Fieldset` with two `DateTimePicker` fields |
| `time_range`     | `Fieldset` with two `TimePicker` fields |
| `select` / `enum` | `Select` with options from `config.options` |
| `multi_select`   | `CheckboxList`       |
| `relationship`   | searchable `Select` pulling from `custom-fields.relationships.targets` |
| `json`           | `Textarea` with JSON encode/decode |

`CustomFieldInfolist` maps the same types to read-only entries: booleans use
icons; dates and times use Filament's display formatting; select values and
relationships resolve to labels; multi-select values render as badges; text
and JSON values span the full width.

## Type configuration

The definition resource exposes type-specific configuration supplied by the
core package. For temporal fields this currently includes:

- `time`: optional `config.step_minutes`
- `time_range`: optional `config.step_minutes` and `config.allow_overnight`

Date, datetime, time, and their range variants expose type-aware validation
controls for required, fixed `min` / `max`, and relative date/datetime bounds.
Relative date rules use the `today` anchor; relative datetime rules use `now`.
Time fields intentionally support fixed bounds only.

## Conditional visibility

Forms generated by `CustomFieldForm::make()` automatically wire each field's
`conditional_visibility` rules into Filament's reactive
`visible(fn (Get $get) => ...)` callbacks — no extra setup. For server-side
enforcement (e.g. preventing values from being saved for hidden fields), use
the included `ConditionalVisibility` rule.

## Grouping

Definitions are rendered into a two-level layout:

- `group_level_1` → outer collapsible `Section`
- `group_level_2` → inner collapsible `Section` (defaults to "General")
- `sort_order` → order within a group

Definitions with no `group_level_1` are emitted at the top level.

## Development

The following Composer scripts are available for local quality checks:

```bash
# Format code automatically
composer lint

# Run all checks that CI runs
composer test

# Individual checks
composer test:lint        # Rector + Pint (dry-run)
composer test:type:check  # PHPStan Level 8
composer test:unit        # Pest unit tests

# Additional scripts (enforced by #7)
composer test:parallel       # Parallel unit tests
composer test:integration    # Integration tests
composer test:type:coverage  # Type coverage with Pest
composer update:snapshots    # Update Pest snapshots
```

The `composer test` aggregate runs the full package quality gate: lint,
type-check, type coverage, unit tests, parallel tests, and integration tests.

## License

MIT — see [LICENSE.md](LICENSE.md).
