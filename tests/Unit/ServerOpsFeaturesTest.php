<?php

namespace Tests\Unit;

use Meta\AdminCore\Features\BackupFeature;
use Meta\AdminCore\Features\FirewallFeature;
use PHPUnit\Framework\TestCase;

class ServerOpsFeaturesTest extends TestCase
{
    public function test_backup_feature_metadata(): void
    {
        $f = new BackupFeature;
        $this->assertSame('backup', $f->name());
        $this->assertTrue($f->available());
        $this->assertNotSame('', $f->label());
        $this->assertNotSame('', $f->description());
        $this->assertStringStartsWith('fa-', $f->icon());
    }

    public function test_server_ops_feature_names_are_distinct(): void
    {
        $this->assertNotSame((new FirewallFeature)->name(), (new BackupFeature)->name());
    }
}
