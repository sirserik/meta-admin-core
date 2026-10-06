<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Root-агент бэкапов запускается cron'ом раз в минуту из домашнего каталога
 * root. Старый цикл `for f in $(ls -1tr "$REQ"/*.json)` при пустой очереди
 * превращался в голый `ls` и «обрабатывал» содержимое текущего каталога —
 * на ETU так из /root уехали в spool/done резервные копии. Тесты гоняют
 * сгенерированный скрипт и смотрят именно на это.
 */
class BackupAgentScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/backup-agent-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/bin', 0700, true);
        mkdir($this->dir . '/cwd/backups', 0700, true);
        file_put_contents($this->dir . '/cwd/key.pem', 'secret');
        file_put_contents($this->dir . '/cwd/dump.json', '{}');
        // flock есть не везде (macOS) — подставляем пропускающий.
        file_put_contents($this->dir . '/bin/flock', "#!/bin/sh\nexit 0\n");
        chmod($this->dir . '/bin/flock', 0755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_empty_queue_leaves_current_directory_alone(): void
    {
        $this->runAgent();

        $this->assertFileExists($this->dir . '/cwd/key.pem');
        $this->assertFileExists($this->dir . '/cwd/dump.json');
        $this->assertDirectoryExists($this->dir . '/cwd/backups');
        $this->assertSame([], $this->done(), 'в done/ ничего не попало');
    }

    public function test_request_is_processed_and_moved_to_done(): void
    {
        mkdir($this->dir . '/spool/requests', 0700, true);
        file_put_contents($this->dir . '/spool/requests/1.json', json_encode(['action' => 'nope']));

        $this->runAgent();

        $this->assertSame(['1.json'], $this->done());
        $status = json_decode((string) file_get_contents($this->dir . '/spool/status.json'), true);
        $this->assertSame('error', $status['state']);
        $this->assertFileExists($this->dir . '/cwd/key.pem', 'текущий каталог по-прежнему не трогаем');
    }

    private function runAgent(): void
    {
        $script = strtr((string) file_get_contents(__DIR__ . '/../../stubs/backup/backup-agent.sh'), [
            '{{SPOOL}}'       => $this->dir . '/spool',
            '{{DB_DIR}}'      => $this->dir . '/db',
            '{{FILES_DIR}}'   => $this->dir . '/files',
            '{{ENV_FILE}}'    => $this->dir . '/.env',
            '{{APP_BASE}}'    => $this->dir . '/app',
            '{{PREFIX}}'      => 'app',
            '{{KEEP_DAYS}}'   => '30',
            '{{FILES_PATHS}}' => 'storage',
            '{{WEB_GROUP}}'   => '',
        ]);
        // Каталог блокировки root'а тесту недоступен — переносим во временный.
        $script = str_replace('/var/lock/admin-core-backup.lock', $this->dir . '/agent.lock', $script);
        file_put_contents($this->dir . '/agent.sh', $script);

        $cmd = sprintf(
            'cd %s && PATH=%s:$PATH bash %s 2>&1',
            escapeshellarg($this->dir . '/cwd'),
            escapeshellarg($this->dir . '/bin'),
            escapeshellarg($this->dir . '/agent.sh'),
        );
        exec($cmd, $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    /** @return list<string> */
    private function done(): array
    {
        $files = glob($this->dir . '/spool/done/*') ?: [];
        sort($files);

        return array_map('basename', $files);
    }
}
