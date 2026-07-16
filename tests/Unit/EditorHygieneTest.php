<?php

namespace Tests\Unit;

use Meta\AdminCore\Services\EditorHygiene;
use PHPUnit\Framework\TestCase;

class EditorHygieneTest extends TestCase
{
    public function test_strips_google_docs_guid_span(): void
    {
        $in = '<p><span id="docs-internal-guid-abc-1234">Текст</span></p>';
        $out = EditorHygiene::cleanGoogleDocs($in);
        $this->assertStringNotContainsString('docs-internal-guid', $out);
        $this->assertStringContainsString('Текст', $out);
    }

    public function test_drops_junk_inline_styles_but_keeps_meaningful(): void
    {
        $in = '<p style="font-variant-numeric:normal;color:#C41E3A;font-weight:normal">Hi</p>';
        $out = EditorHygiene::cleanGoogleDocs($in);
        $this->assertStringNotContainsString('font-variant-numeric', $out);
        $this->assertStringNotContainsString('font-weight', $out);
        $this->assertStringContainsString('color:#C41E3A', $out);
    }

    public function test_removes_empty_paragraphs(): void
    {
        $in = '<p>Keep</p><p>&nbsp;</p><p><br></p>';
        $out = EditorHygiene::cleanGoogleDocs($in);
        $this->assertSame('<p>Keep</p>', $out);
    }

    public function test_paragraphs_to_lists(): void
    {
        $in = '<p>первый;</p><p>второй;</p><p>третий;</p>';
        $out = EditorHygiene::paragraphsToLists($in);
        $this->assertStringContainsString('<ul>', $out);
        $this->assertSame(3, substr_count($out, '<li>'));
        $this->assertStringContainsString('<li>первый</li>', $out);
    }

    public function test_paragraphs_to_lists_respects_min(): void
    {
        $in = '<p>один;</p>'; // single item, below min=2
        $this->assertSame($in, EditorHygiene::paragraphsToLists($in));
    }

    public function test_clean_editor_artifacts_unwraps_li_p(): void
    {
        $in = '<ul><li><p><a href="/media/doc.pdf">Doc (PDF)</a></p></li><li><p>Plain</p></li></ul>';
        $out = EditorHygiene::cleanEditorArtifacts($in);
        $this->assertSame('<ul><li><a href="/media/doc.pdf">Doc (PDF)</a></li><li>Plain</li></ul>', $out);
    }

    public function test_clean_editor_artifacts_keeps_multi_paragraph_li(): void
    {
        $in = '<ul><li><p>Первый абзац</p><p>Второй абзац</p></li></ul>';
        $this->assertSame($in, EditorHygiene::cleanEditorArtifacts($in));
    }

    public function test_clean_editor_artifacts_removes_empty_paragraphs(): void
    {
        $in = '<p></p><h3>Title</h3><p>Keep</p><p>&nbsp;</p><p><br></p><p class="x"> </p>';
        $out = EditorHygiene::cleanEditorArtifacts($in);
        $this->assertSame('<h3>Title</h3><p>Keep</p>', $out);
    }

    public function test_clean_editor_artifacts_leaves_styles_and_spans_alone(): void
    {
        $in = '<p style="color:#C41E3A"><span style="font-weight:700">Hi</span></p>';
        $this->assertSame($in, EditorHygiene::cleanEditorArtifacts($in));
    }

    public function test_extract_base64_replaces_with_url_and_persists(): void
    {
        // 1x1 transparent PNG
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
        $in = '<img src="data:image/png;base64,' . $png . '"> after';
        $seen = [];
        $out = EditorHygiene::extractBase64($in, function (string $filename, string $bytes) use (&$seen) {
            $seen[] = $filename;
            return '/storage/uploads/extracted/' . $filename;
        });
        $this->assertStringNotContainsString('data:image', $out);
        $this->assertStringContainsString('/storage/uploads/extracted/', $out);
        $this->assertCount(1, $seen);
        $this->assertStringEndsWith('.png', $seen[0]);
    }
}
