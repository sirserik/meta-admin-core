<?php

namespace Tests\Unit;

use Meta\AdminCore\Models\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function test_formatted_file_size(): void
    {
        $this->assertSame('512 B', (new Document(['file_size' => 512]))->formatted_file_size);
        $this->assertSame('1.50 KB', (new Document(['file_size' => 1536]))->formatted_file_size);
        $this->assertSame('2.00 MB', (new Document(['file_size' => 2097152]))->formatted_file_size);
    }

    public function test_file_icon_maps_known_types_and_falls_back(): void
    {
        $this->assertSame('fa-file-pdf', (new Document(['file_type' => 'pdf']))->file_icon);
        $this->assertSame('fa-file-word', (new Document(['file_type' => 'docx']))->file_icon);
        $this->assertSame('fa-file', (new Document(['file_type' => 'xyz']))->file_icon);
    }

    public function test_supported_types_and_mime_rule_are_consistent(): void
    {
        $this->assertContains('pdf', Document::getSupportedFileTypes());
        $this->assertStringContainsString('application/pdf', Document::getMimeTypesRule());
    }
}
