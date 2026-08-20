<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_refuses_sqlite_test_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));

        $code = Artisan::call('rodante:backup');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('solo soporta MySQL', Artisan::output());
    }

    public function test_restore_requires_force_flag(): void
    {
        $code = Artisan::call('rodante:restore', ['file' => 'missing.sql.gz']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('--force', Artisan::output());
    }

    public function test_ops_docs_exist(): void
    {
        $this->assertFileExists(base_path('docs/BACKUP.md'));
        $this->assertFileExists(base_path('docs/ROLLBACK.md'));
        $this->assertStringContainsString('rodante:backup', file_get_contents(base_path('docs/BACKUP.md')));
        $this->assertStringContainsString('migrate:fresh', file_get_contents(base_path('docs/ROLLBACK.md')));
    }
}
