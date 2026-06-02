<?php

namespace Tests\Unit;

use Meta\AdminCore\Services\MediaIntegrity;
use PHPUnit\Framework\TestCase;

class MediaIntegrityTest extends TestCase
{
    public function test_detects_html_masquerading_as_image(): void
    {
        $this->assertTrue(MediaIntegrity::isCorrupt('webp', "<!DOCTYPE html>\n<html>..."));
        $this->assertTrue(MediaIntegrity::isCorrupt('jpeg', '<html lang="ru">'));
        $this->assertTrue(MediaIntegrity::isCorrupt('pdf', "  <!doctype html>"));
        $this->assertTrue(MediaIntegrity::isCorrupt('png', "\xEF\xBB\xBF<!DOCTYPE html>")); // BOM
    }

    public function test_real_binary_images_pass(): void
    {
        $this->assertFalse(MediaIntegrity::isCorrupt('webp', 'RIFF' . str_repeat("\x00", 8) . 'WEBP'));
        $this->assertFalse(MediaIntegrity::isCorrupt('jpeg', "\xFF\xD8\xFF\xE0\x00\x10JFIF"));
        $this->assertFalse(MediaIntegrity::isCorrupt('png', "\x89PNG\r\n\x1a\n"));
    }

    public function test_non_binary_extensions_are_never_flagged(): void
    {
        // svg/xml/html legitimately contain markup → not "corrupt"
        $this->assertFalse(MediaIntegrity::isBinaryImageExt('svg'));
        $this->assertFalse(MediaIntegrity::isCorrupt('svg', '<svg xmlns="...">'));
        $this->assertFalse(MediaIntegrity::isCorrupt('xml', '<?xml version="1.0"?>'));
    }
}
