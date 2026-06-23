<?php

namespace ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected $keyType = 'string';

    protected $table = 'cf_filament_organizations';

    /**
     * Mirrors the real-world Employee accessor: a stored column wins, otherwise the
     * value is composed from sibling columns. Exercises the relationship resolver
     * against a display field whose value depends on attributes beyond itself.
     *
     * @return Attribute<string, string>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(
            fn (): string => ($this->attributes['display_name'] ?? null) ?: trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
        );
    }
}
