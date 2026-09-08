<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Executes the generated root script against fake `ufw` and `psql` binaries.
 *
 * The point of these tests is one incident: bad DB credentials in `.env` made
 * `psql` fail, the failure was swallowed, an empty result was read as «no
 * addresses wanted» — and the next cron tick removed every allowed address
 * from ufw, SSH included. So the interesting case here is not the happy path
 * but the broken one: a failed read must never delete a rule.
 */
class FirewallSyncScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/fw-sync-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/bin', 0700, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_failed_table_read_keeps_every_rule(): void
    {
        $this->fakeUfw(['37.99.0.0/16', '95.82.117.6', '203.0.113.9']);
        $this->fakePsql(exit: 2, stderr: 'FATAL: password authentication failed for user "serik"');

        $out = $this->runSync();

        $this->assertSame([], $this->ufwCalls('delete'), 'ничего не удаляем, пока список неизвестен');
        $this->assertStringContainsString('ERR could not read', $out);
        $this->assertStringContainsString('password authentication failed', $out, 'ошибку БД видно в логе, а не проглатываем');
    }

    public function test_failed_table_read_still_restores_the_emergency_block(): void
    {
        $this->fakeUfw(['95.82.117.6']);           // аварийного блока в ufw нет
        $this->fakePsql(exit: 2, stderr: 'connection refused');

        $this->runSync();

        $this->assertSame(['37.99.0.0/16'], $this->ufwCalls('allow'));
        $this->assertSame([], $this->ufwCalls('delete'));
    }

    public function test_successful_read_reconciles_both_ways(): void
    {
        $this->fakeUfw(['37.99.0.0/16', '203.0.113.9']);   // .9 в ufw, но не в таблице
        $this->fakePsql(rows: ['95.82.117.6']);            // .6 в таблице, но не в ufw

        $this->runSync();

        $this->assertSame(['95.82.117.6'], $this->ufwCalls('allow'));
        $this->assertSame(['203.0.113.9'], $this->ufwCalls('delete'));
    }

    public function test_empty_table_keeps_only_the_emergency_block(): void
    {
        $this->fakeUfw(['37.99.0.0/16', '203.0.113.9']);
        $this->fakePsql(rows: []);

        $this->runSync();

        $this->assertSame(['203.0.113.9'], $this->ufwCalls('delete'), 'пустая таблица — законное состояние, чистим');
        $this->assertSame([], $this->ufwCalls('allow'));
    }

    public function test_garbage_row_is_ignored_not_passed_to_ufw(): void
    {
        $this->fakeUfw(['37.99.0.0/16']);
        $this->fakePsql(rows: ['203.0.113.7; rm -rf /', '256.1.1.1', '10.0.0.5']);

        $out = $this->runSync();

        $this->assertSame(['10.0.0.5'], $this->ufwCalls('allow'));
        $this->assertStringContainsString('SKIP invalid ip from db', $out);
    }

    public function test_missing_db_name_touches_nothing(): void
    {
        $this->fakeUfw(['37.99.0.0/16', '203.0.113.9']);
        $this->fakePsql(rows: []);

        $out = $this->runSync(env: "DB_CONNECTION=pgsql\nDB_HOST=127.0.0.1\n");

        $this->assertSame([], $this->ufwCalls('delete'));
        $this->assertStringContainsString('no DB_DATABASE', $out);
    }

    // --- helpers -------------------------------------------------------

    /** Render the stub the way the artisan command does. */
    private function script(string $envFile): string
    {
        $stub = file_get_contents(__DIR__ . '/../../stubs/firewall/firewall-sync.sh');

        return strtr($stub, [
            '{{EMERGENCY_IPS}}' => '37.99.0.0/16',
            '{{ENV_FILE}}'      => $envFile,
            '{{TABLE}}'         => 'firewall_rules',
            '{{COMMENT}}'       => 'admin-core-allowlist',
        ]);
    }

    /** Fake `ufw`: prints the given 22/tcp sources on `status`, logs every call. */
    private function fakeUfw(array $allowed): void
    {
        $status = "Status: active\n\nTo                         Action      From\n--                         ------      ----\n";
        foreach ($allowed as $ip) {
            $status .= sprintf("22/tcp                     ALLOW       %s                # admin-core-allowlist\n", $ip);
        }

        $this->writeBin('ufw', <<<SH
        #!/bin/bash
        if [[ "\$1" == "status" ]]; then
            cat <<'STATUS'
        {$status}
        STATUS
            exit 0
        fi
        echo "\$*" >> "{$this->dir}/ufw.calls"
        exit 0
        SH);
    }

    /** Fake `psql`: either prints rows and exits 0, or fails like a real client. */
    private function fakePsql(array $rows = [], int $exit = 0, string $stderr = ''): void
    {
        $body = $exit === 0
            ? 'printf "%s" ' . escapeshellarg(implode("\n", $rows) . ($rows === [] ? '' : "\n"))
            : 'echo ' . escapeshellarg($stderr) . ' >&2';

        $this->writeBin('psql', "#!/bin/bash\n{$body}\nexit {$exit}\n");
    }

    private function writeBin(string $name, string $body): void
    {
        // Heredocs above are indented for readability — strip the padding.
        $body = preg_replace('/^        /m', '', $body);
        $path = $this->dir . '/bin/' . $name;
        file_put_contents($path, $body);
        chmod($path, 0700);
    }

    private function runSync(?string $env = null): string
    {
        $envFile = $this->dir . '/.env';
        file_put_contents($envFile, $env ?? "DB_CONNECTION=pgsql\nDB_DATABASE=etu\nDB_USERNAME=meta\nDB_PASSWORD=secret\nDB_HOST=127.0.0.1\n");

        $script = $this->dir . '/sync.sh';
        file_put_contents($script, $this->script($envFile));
        chmod($script, 0700);

        $cmd = sprintf(
            'PATH=%s:/usr/bin:/bin /bin/bash %s 2>&1',
            escapeshellarg($this->dir . '/bin'),
            escapeshellarg($script),
        );

        return (string) shell_exec($cmd);
    }

    /** Addresses passed to `ufw allow …` / `ufw delete allow …`, in call order. */
    private function ufwCalls(string $kind): array
    {
        $file = $this->dir . '/ufw.calls';
        if (! is_file($file)) {
            return [];
        }

        $ips = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $isDelete = str_starts_with($line, 'delete ');
            if (($kind === 'delete') !== $isDelete) {
                continue;
            }
            if (preg_match('/from (\S+)/', $line, $m)) {
                $ips[] = $m[1];
            }
        }

        return $ips;
    }
}
