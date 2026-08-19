<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCounter extends Model
{
    public $timestamps = false;

    protected $fillable = ['company_id', 'document', 'value'];
}
