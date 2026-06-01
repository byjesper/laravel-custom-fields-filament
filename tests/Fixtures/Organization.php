<?php

namespace Yezper\LaravelCustomFieldsFilament\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected $keyType = 'string';

    protected $table = 'cf_filament_organizations';
}
