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

    /**
     * Rotate an already-uploaded image in place (90/180/270° clockwise).
     * Accepts a path or a full media URL; the file keeps its path, so all
     * references (blocks JSON, model columns, editor content) stay valid.
     * A linked Media record, if any, gets fresh width/height/size.
     */
    public function rotateImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path'    => 'required|string|max:2048',
            'degrees' => 'required|integer|in:90,180,270,-90',
        ]);

        // полный URL или /storage-префикс → путь внутри public-диска
        $path = parse_url($data['path'], PHP_URL_PATH) ?? $data['path'];
        $path = ltrim(preg_replace('#^/?(storage|media)/#', '', $path), '/');

        $meta = $this->imageService->rotate($path, (int) $data['degrees']);
        if (!$meta) {
            return response()->json(['success' => false, 'message' => 'Файл не найден или формат не поддерживает поворот'], 422);
        }

        \Meta\AdminCore\Models\Media::where('path', $path)->update([
            'width'  => $meta['width'],
            'height' => $meta['height'],
            'size'   => $meta['size'],
        ]);

        return response()->json([
            'success' => true,
            // тот же путь; ?v= — только для обновления превью в админке
            'url'     => media_url($path) . '?v=' . time(),
            'path'    => $path,
            'width'   => $meta['width'],
            'height'  => $meta['height'],
        ]);
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
