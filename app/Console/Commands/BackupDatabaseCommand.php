<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'rodante:backup
                            {--keep=14 : Cantidad de backups a conservar}
                            {--dir= : Directorio destino (default storage/app/private/backups)}';

    protected $description = 'Dump lógico de MySQL a storage (gzip). Para restauración ver docs/BACKUP.md.';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') !== 'mysql') {
            $this->error('rodante:backup solo soporta MySQL. Driver actual: '.($config['driver'] ?? 'desconocido'));

            return self::FAILURE;
        }

        if (! $this->binaryExists('mysqldump')) {
            $this->error('mysqldump no está en el PATH. Instalalo (default-mysql-client) o usá scripts/backup-mysql.sh.');

            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: storage_path('app/private/backups');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd-His');
        $database = $config['database'];
        $file = $dir.DIRECTORY_SEPARATOR."rodante-{$database}-{$stamp}.sql.gz";

        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $user = $config['username'] ?? '';
        $password = (string) ($config['password'] ?? '');

        $dump = [
            'mysqldump',
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--no-tablespaces',
            '-h', $host,
            '-P', $port,
            '-u', $user,
            $database,
        ];

        $this->info('Generando backup: '.$file);
        $process = Process::fromShellCommandline(
            implode(' ', array_map('escapeshellarg', $dump)).' | gzip -c > '.escapeshellarg($file),
            base_path(),
            array_merge($_ENV, [
                'MYSQL_PWD' => $password,
            ]),
            null,
            600,
        );
        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) < 32) {
            @unlink($file);
            Log::error('Backup MySQL falló', [
                'exit' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
                'file' => $file,
            ]);
            $this->error('Falló mysqldump: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        $this->prune($dir, (int) $this->option('keep'));
        Log::info('Backup MySQL OK', [
            'file' => $file,
            'bytes' => filesize($file),
        ]);
        $this->info('Listo ('.number_format(filesize($file)).' bytes).');

        return self::SUCCESS;
    }

    private function prune(string $dir, int $keep): void
    {
        if ($keep < 1) {
            return;
        }
        $files = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();
        foreach ($files->slice($keep) as $old) {
            File::delete($old->getPathname());
            $this->line('Eliminado backup viejo: '.$old->getFilename());
        }
    }

    private function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful();
    }
}
