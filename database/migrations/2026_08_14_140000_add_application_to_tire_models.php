<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tire_models') || Schema::hasColumn('tire_models', 'application')) {
            return;
        }

        Schema::table('tire_models', function (Blueprint $table) {
            $table->string('application')->default('MIXTO')->after('name');
        });

        foreach (DB::table('tire_models')->orderBy('id')->get() as $model) {
            $haystack = mb_strtolower(($model->name ?? '').' '.$model->code);
            $application = 'MIXTO';
            if (str_contains($haystack, 'direcci')) {
                $application = 'DIRECCION';
            } elseif (str_contains($haystack, 'tracci')) {
                $application = 'TRACCION';
            } elseif (str_contains($haystack, 'arrastre')) {
                $application = 'ARRASTRE';
            }

            DB::table('tire_models')->where('id', $model->id)->update(['application' => $application]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tire_models', 'application')) {
            Schema::table('tire_models', function (Blueprint $table) {
                $table->dropColumn('application');
            });
        }
    }
};
