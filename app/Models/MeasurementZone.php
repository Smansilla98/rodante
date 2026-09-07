<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementZone extends Model
{

    use BelongsToCompany;
    public $timestamps = false;

    protected $fillable = [
        'company_id','tire_size_id', 'code', 'name', 'sort_order'];

    public function size(): BelongsTo
    {
        return $this->belongsTo(TireSize::class, 'tire_size_id');
    }
}
