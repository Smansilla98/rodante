<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class MovementReason extends Model
{

    use BelongsToCompany;
    protected $fillable = [
        'company_id','code', 'name', 'applies_to', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
