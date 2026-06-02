<?php

namespace Tests\Unit;

use Meta\AdminCore\Features\FirewallFeature;
use Meta\AdminCore\Models\FirewallRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FirewallFeatureTest extends TestCase
{
    public function test_feature_metadata(): void
    {
        $f = new FirewallFeature;

        $this->assertSame('firewall', $f->name());
        $this->assertTrue($f->available(), 'package is self-contained — always available');
        $this->assertNotSame('', $f->label());
        $this->assertNotSame('', $f->description());
        $this->assertStringStartsWith('fa-', $f->icon());
    }

    #[DataProvider('validAddresses')]
    public function test_accepts_valid_ipv4_and_cidr(string $value): void
    {
        $this->assertTrue($this->passes($value), "{$value} should be accepted");
    }

    public static function validAddresses(): array
    {
        return [
            ['203.0.113.7'],
            ['10.0.0.1'],
            ['192.168.1.0/24'],
            ['0.0.0.0/0'],
            ['255.255.255.255/32'],
        ];
    }

    #[DataProvider('invalidAddresses')]
    public function test_rejects_garbage(string $value): void
    {
        $this->assertFalse($this->passes($value), "{$value} should be rejected");
    }

    public static function invalidAddresses(): array
    {
        return [
            ['not-an-ip'],
            ['203.0.113.7/33'],     // prefix out of range
            ['256.1.1.1'],          // octet out of range
            ['2001:db8::1'],        // IPv6 not supported (ufw rule is v4)
            ['203.0.113.7; rm -rf'], // injection-ish garbage
            [''],
        ];
    }

    /** Run the validation closure and report whether it passed (no $fail call). */
    private function passes(string $value): bool
    {
        $failed = false;
        $rule = FirewallRule::ipOrCidrRule();
        $rule('ip_address', $value, function () use (&$failed) { $failed = true; });

        return ! $failed;
    }
}
