<?php
declare(strict_types=1);

namespace Automatizador;

/**
 * Bitácora simple: imprime en consola y agrega a un archivo diario en logs/.
 */
final class Logger
{
    public function __construct(private string $logDir)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }

    public function info(string $msg): void
    {
        $this->write('INFO', $msg);
    }

    public function error(string $msg): void
    {
        $this->write('ERROR', $msg);
    }

    private function write(string $level, string $msg): void
    {
        $line = sprintf("[%s] %-5s %s", date('Y-m-d H:i:s'), $level, $msg);
        // En CLI (cron/watcher) se imprime en consola; en web (HTTP) no hay STDOUT/STDERR.
        if (PHP_SAPI === 'cli' && defined('STDOUT')) {
            fwrite($level === 'ERROR' ? STDERR : STDOUT, $line . PHP_EOL);
        }
        $file = rtrim($this->logDir, '/') . '/' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
    }
}
