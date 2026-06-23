# Changelog

All notable changes to `byjesper/laravel-custom-fields-filament` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.2] - 2026-06-23

### Fixed
- Relationship custom fields whose `display_field` is a derived accessor (e.g.
  an `Employee::display_name` that falls back to `first_name . ' ' . last_name`)
  now render correctly. `primeRelationshipLabels()` previously used `pluck()`,
  which hydrates a partial model — the accessor saw only the display column and
  collapsed to whitespace. It now hydrates full models in a single query. Added
  a regression test covering an accessor-based display field.
- Restore Pint code style after the `byjesper` vendor rename.

## [2.0.1] - 2026-06-23

### Changed
- Resolve `byjesper/laravel-custom-fields` from Packagist; dropped the VCS
  `repositories` entry now that the dependency is published.

## [2.0.0] - 2026-06-23

### Changed
- **Breaking:** renamed vendor and namespace `yezper` → `byjesper`
  (`Yezper\LaravelCustomFieldsFilament\` → `ByJesper\LaravelCustomFieldsFilament\`).
  Now requires `byjesper/laravel-custom-fields ^2.0`.
