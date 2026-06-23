<?php

namespace ByJesper\LaravelCustomFieldsFilament\Tests\Fixtures;

use ByJesper\LaravelCustomFields\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasCustomFields;

    public $incrementing = false;

    protected $guarded = [];

    protected $keyType = 'string';

    protected $table = 'cf_filament_contacts';

    protected string $customFieldEntityType = 'contact';
}
