<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'rodante:restore
                            {file : Ruta al .sql o .sql.gz}
                            {--force : Confirma que vas a pisar la base actual}';

    protected $description = 'Restaura un dump MySQL. Destruye datos actuales. Ver docs/BACKUP.md y docs/ROLLBACK.md.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Falta --force. Esto pisa la base configurada en .env.');

            return self::FAILURE;
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        if (($config['driver'] ?? '') !== 'mysql') {
            $this->error('rodante:restore solo soporta MySQL.');

            return self::FAILURE;
        }

        $file = $this->argument('file');
        if (! is_file($file)) {
            $alt = storage_path('app/private/backups/'.$file);
            if (is_file($alt)) {
                $file = $alt;
            }
        }
        if (! is_file($file)) {
            $this->error('No existe el archivo: '.$this->argument('file'));

            return self::FAILURE;
        }

        if (! $this->binaryExists('mysql')) {
            $this->error('Cliente mysql no está en el PATH.');

            return self::FAILURE;
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $user = $config['username'] ?? '';
        $password = (string) ($config['password'] ?? '');
        $database = $config['database'];

        $reader = str_ends_with($file, '.gz')
            ? 'gzip -dc '.escapeshellarg($file)
            : 'cat '.escapeshellarg($file);

        $mysql = implode(' ', array_map('escapeshellarg', [
            'mysql',
            '-h', $host,
            '-P', $port,
            '-u', $user,
            $database,
        ]));

        $this->warn('Restaurando '.$file.' → '.$database.'@'.$host);
        $process = Process::fromShellCommandline(
            $reader.' | '.$mysql,
            base_path(),
            array_merge($_ENV, ['MYSQL_PWD' => $password]),
            null,
            600,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('Restore MySQL falló', [
                'file' => $file,
                'stderr' => $process->getErrorOutput(),
            ]);
            $this->error($process->getErrorOutput());

            return self::FAILURE;
        }

        Log::warning('Restore MySQL completado', [
            'file' => $file,
            'database' => $database,
            'user_id' => null,
        ]);
        $this->info('Restore OK.');

        return self::SUCCESS;
    }

    private function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful();
    }
}
