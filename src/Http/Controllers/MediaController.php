<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\Media;
use Meta\AdminCore\Services\ImageService;

class MediaController extends Controller
{
    public function __construct(private ImageService $imageService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'folder', 'type']);
        $query = Media::query()->latest();

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('filename', 'like', "%{$term}%")
                  ->orWhere('alt',     'like', "%{$term}%");
            });
        }
        if (!empty($filters['folder'])) $query->where('folder', $filters['folder']);
        if (($filters['type'] ?? '') === 'images') {
            $query->where('mime_type', 'like', 'image/%');
        }

        $paginator = $query->paginate(48)->withQueryString();
        $paginator->getCollection()->transform(fn (Media $m) => [
            'id'         => $m->id,
            'filename'   => $m->filename,
            'path'       => $m->path,
            'url'        => $m->path ? media_url($m->path) : null,
            'mime_type'  => $m->mime_type,
            'size'       => $m->size,
            'width'      => $m->width,
            'height'     => $m->height,
            'alt'        => $m->alt,
            'folder'     => $m->folder,
            'is_image'   => (bool) $m->mime_type && str_starts_with($m->mime_type, 'image/'),
            'created_at' => optional($m->created_at)->format('d.m.Y H:i'),
        ]);

        return Inertia::render('Media/Index', [
            'title'   => 'Медиа-библиотека',
            'items'   => $paginator,
            'folders' => Media::query()->distinct()->orderBy('folder')->pluck('folder'),
            'stats'   => [
                'total'  => Media::count(),
                'images' => Media::where('mime_type', 'like', 'image/%')->count(),
                'size'   => Media::sum('size'),
            ],
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files'   => 'required',
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip',
            'folder'  => 'nullable|string|max:50',
        ]);

        $folder = $request->input('folder', 'uploads');
        $count = 0;

        foreach ($request->file('files') as $file) {
            $mime = $file->getMimeType() ?? '';
            $isImage = str_starts_with($mime, 'image/') && !str_contains($mime, 'svg');

            if ($isImage) {
                $path = $this->imageService->upload($file, $folder, 1920, 1080);
                $storedMime = 'image/webp';
                $sp = Storage::disk('public')->path($path);
                $dim = @getimagesize($sp);
                $size = Storage::disk('public')->size($path);
            } else {
                $path = $file->store($folder, 'public');
                $storedMime = $mime;
                $dim  = null;
                $size = $file->getSize();
            }

            Media::create([
                'filename'    => $file->getClientOriginalName(),
                'path'        => $path,
                'disk'        => 'public',
                'mime_type'   => $storedMime,
                'size'        => $size,
                'width'       => $dim[0] ?? null,
                'height'      => $dim[1] ?? null,
                'folder'      => $folder,
                'uploaded_by' => auth()->id(),
            ]);
            $count++;
        }

        return back()->with('success', "Загружено файлов: {$count}");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $medium = Media::findOrFail($id);
        $data = $request->validate([
            'alt'    => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:50',
            'title'  => 'nullable|string|max:255',
        ]);
        $medium->update($data);
        return back()->with('success', 'Файл обновлён');
    }

    /**
     * Rotate an image in place (same path — all references stay valid)
     * and refresh the record's dimensions/size.
     */
    public function rotate(Request $request, int $id): RedirectResponse
    {
        $medium = Media::findOrFail($id);
        $data = $request->validate(['degrees' => 'required|integer|in:90,180,270,-90']);

        $meta = $this->imageService->rotate($medium->path, (int) $data['degrees']);
        if (!$meta) {
            return back()->with('error', 'Файл не найден или формат не поддерживает поворот');
        }

        $medium->update(['width' => $meta['width'], 'height' => $meta['height'], 'size' => $meta['size']]);
        return back()->with('success', 'Изображение повёрнуто');
    }

    public function destroy(int $id): RedirectResponse
    {
        $medium = Media::findOrFail($id);
        $medium->delete(); // booted() hook removes the file from disk
        return back()->with('success', 'Файл удалён');
    }
}
