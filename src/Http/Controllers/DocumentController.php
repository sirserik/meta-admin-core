<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Meta\AdminCore\Contracts\PubliclyVisible;
use Meta\AdminCore\Models\Document;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Manage and serve polymorphic Documents. Admin actions (store/update/
 * destroy/reorder) sit behind the admin middleware; download/view are
 * public but gate anonymous access to files on non-public parents.
 *
 * Inline rendering is never allowed — files are always sent as attachments
 * with nosniff + a locked-down CSP, so a payload smuggled into a tolerated
 * mime can't execute under the app's session/origin.
 */
class DocumentController extends Controller
{
    private function dir(): string
    {
        return trim((string) config('admin-core.documents.dir', 'documents'), '/');
    }

    public function store(Request $request)
    {
        $allowed = (array) config('admin-core.documents.attachable', []);
        $data = $request->validate([
            'documentable_type' => ['required', 'string', 'in:' . implode(',', $allowed)],
            'documentable_id'   => ['required', 'integer'],
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'locale'            => ['nullable', 'string', 'max:8'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'file'              => ['required', 'file', 'max:' . (int) config('admin-core.documents.max_kb', 51200),
                                    'mimes:' . implode(',', Document::getSupportedFileTypes())],
        ]);

        $file = $request->file('file');
        $path = $file->store($this->dir(), 'public');

        $doc = Document::create([
            'documentable_type' => $data['documentable_type'],
            'documentable_id'   => $data['documentable_id'],
            'title'             => $data['title'],
            'description'       => $data['description'] ?? null,
            'locale'            => $data['locale'] ?? null,
            'sort_order'        => $data['sort_order'] ?? 0,
            'file_path'         => $path,
            'file_name'         => $file->getClientOriginalName(),
            'file_type'         => strtolower($file->getClientOriginalExtension()),
            'file_size'         => $file->getSize(),
            'mime_type'         => $file->getMimeType(),
        ]);

        return response()->json(['success' => true, 'document' => $doc]);
    }

    public function update(Request $request, Document $document)
    {
        $document->update($request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'locale'      => ['nullable', 'string', 'max:8'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]));

        return response()->json(['success' => true, 'document' => $document]);
    }

    public function destroy(Document $document)
    {
        $document->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $ids = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']])['order'];
        $known = Document::whereIn('id', $ids)->pluck('id')->all();
        abort_if(count($known) !== count(array_unique($ids)), 422, 'Неизвестные идентификаторы документов');

        Document::upsert(
            array_map(fn ($id, $i) => ['id' => $id, 'sort_order' => $i], $ids, array_keys($ids)),
            ['id'], ['sort_order']
        );

        return response()->json(['success' => true]);
    }

    public function download(Request $request, Document $document)
    {
        $this->ensureReadable($request, $document);
        $document->incrementDownloads();

        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path, $document->file_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function view(Request $request, Document $document)
    {
        $this->ensureReadable($request, $document);
        $document->incrementDownloads();

        $full = storage_path('app/public/' . $document->file_path);
        abort_unless(is_file($full), 404);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $document->file_name,
            preg_replace('/[^\x20-\x7e]/', '_', $document->file_name)
        );

        return response()->file($full, [
            'Content-Type'            => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition'     => $disposition,
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy'  => "default-src 'none'",
        ]);
    }

    /**
     * Admins always read. Anonymous users read only if the parent is public:
     * a documentable that implements PubliclyVisible must return true;
     * documentables that don't implement it are treated as public.
     */
    private function ensureReadable(Request $request, Document $document): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole((array) config('admin-core.admin_roles', ['admin']))) {
            return;
        }

        $parent = $document->documentable;
        if ($parent instanceof PubliclyVisible) {
            abort_unless($parent->isPubliclyVisible(), 404);
        }
    }
}
