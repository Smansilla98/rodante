<?php

use App\Support\TireProductCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TireProductCatalog::sync();
    }

    public function down(): void
    {
        //
    }
};
