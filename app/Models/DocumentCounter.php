<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DocumentCounter extends Model
{

    use BelongsToCompany;
    public $timestamps = false;

    protected $fillable = ['company_id', 'document', 'value'];
}
