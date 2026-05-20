<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Meta\AdminCore\Services\ImageService;

/**
 * Generic upload endpoints used by the rich text editor and the
 * PageBlock data editor. Two endpoints:
 *
 *   POST /admin/upload/image  — images (jpg/png/gif/webp/svg),
 *       converted to WebP via ImageService.
 *   POST /admin/upload/file   — any file (PDF, doc, xlsx, zip, …),
 *       stored as-is in `uploads/files/`.
 *
 * Consumer sites with an existing `App\Http\Controllers\Admin\UploadController`
 * that binds /admin/upload/image take precedence since their route
 * loads first — leave the old one alone, just use our /admin/upload/file.
 */
class UploadController extends Controller
{
    public function __construct(protected ImageService $imageService) {}

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            // SVG intentionally dropped — inline SVG can carry <script>
            // and we'd serve it from the same origin under user sessions.
            // Sites that genuinely need SVG uploads should override this
            // endpoint with their own controller and sanitize first.
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $path = $this->imageService->uploadOriginal($request->file('file'), 'uploads/editor');
        return response()->json(['success' => true, 'url' => media_url($path)]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,txt,rtf,csv',
        ]);

        $file = $request->file('file');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName) ?: 'file';
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = time() . '_' . Str::slug($safeName) . '.' . $ext;
        $path = $file->storeAs('uploads/files', $filename, 'public');

        return response()->json([
            'success'  => true,
            'url'      => media_url($path),
            'filename' => $file->getClientOriginalName(),
            'size'     => $file->getSize(),
            'ext'      => $ext,
        ]);
    }
}
