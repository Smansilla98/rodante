<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_logs', 'company_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                $table->index(['company_id', 'created_at']);
            });
        }

        if (Schema::hasColumn('users', 'company_id')) {
            DB::table('audit_logs')
                ->whereNull('company_id')
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $companyId = DB::table('users')->where('id', $row->user_id)->value('company_id');
                        if ($companyId) {
                            DB::table('audit_logs')->where('id', $row->id)->update(['company_id' => $companyId]);
                        }
                    }
                });
        }

        $fallback = DB::table('companies')->orderBy('id')->value('id');
        if ($fallback) {
            DB::table('audit_logs')->whereNull('company_id')->update(['company_id' => $fallback]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('audit_logs', 'company_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
