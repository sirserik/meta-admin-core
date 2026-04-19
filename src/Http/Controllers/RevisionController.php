<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Facades\AdminCore;
use Meta\AdminCore\Models\PageBlock;
use Meta\AdminCore\Models\Revision;

/**
 * Revision history for a single row of a registered resource.
 *
 * `/admin/{resource}/{id}/revisions` lists the row's revisions,
 * newest first, with the author and captured attribute payload.
 * POST `/restore/{revId}` writes a past snapshot back to the row
 * (itself producing a fresh revision, so the action is undoable).
 *
 * We re-use AdminCore's resource registry to derive the model
 * class — the caller passes the resource name (the URL segment),
 * not the FQCN.
 */
class RevisionController extends Controller
{
    public function index(Request $request, string $resource, int $id): Response
    {
        $config = AdminCore::getResource($resource);
        abort_unless($config && isset($config['model']), 404);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $config['model'];
        $record = $model::findOrFail($id);

        $revisions = Revision::query()
            ->where('revisionable_type', $record->getMorphClass())
            ->where('revisionable_id', $record->getKey())
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Revision $r) => [
                'id'         => $r->id,
                'created_at' => optional($r->created_at)->format('d.m.Y H:i:s'),
                'user'       => $r->user ? [
                    'id'    => $r->user->id,
                    'name'  => $r->user->name,
                    'email' => $r->user->email,
                ] : null,
                'note'       => $r->note,
                'data'       => $r->data,
            ]);

        return Inertia::render('Revisions/Index', [
            'title'    => 'История: ' . ($config['label'] ?? $resource) . ' #' . $record->getKey(),
            'resource' => $resource,
            'record'   => [
                'id'          => $record->getKey(),
                'label'       => method_exists($record, 'translate')
                                    ? ($record->translate('title', 'ru') ?? $record->title ?? '')
                                    : ($record->title ?? ''),
                'edit_url'    => route('admin.' . $resource . '.edit', ['id' => $record->getKey()]),
            ],
            'revisions' => $revisions,
        ]);
    }

    public function restore(Request $request, string $resource, int $id, int $revisionId): RedirectResponse
    {
        $config = AdminCore::getResource($resource);
        abort_unless($config && isset($config['model']), 404);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $config['model'];
        $record = $model::findOrFail($id);

        if (!method_exists($record, 'restoreRevision')) {
            return back()->withErrors(['revision' => 'Модель не использует trait Revisionable']);
        }

        $ok = $record->restoreRevision($revisionId);
        if (!$ok) {
            return back()->withErrors(['revision' => 'Не удалось восстановить — ревизия не найдена или принадлежит другой записи']);
        }

        return redirect()
            ->route('admin.revisions.index', ['resource' => $resource, 'id' => $id])
            ->with('success', 'Ревизия восстановлена. Текущее состояние сохранено как новая ревизия.');
    }

    /* ------------------------------------------------------------------
     * Built-in PageBlock wrappers. PageBlock has its own dedicated
     * controller (not registered through AdminCore::resource()), so
     * it would otherwise miss the generic revision screens.
     * ------------------------------------------------------------------ */

    public function indexForPageBlock(Request $request, int $id): Response
    {
        $block = PageBlock::findOrFail($id);

        $revisions = Revision::query()
            ->where('revisionable_type', $block->getMorphClass())
            ->where('revisionable_id', $block->getKey())
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Revision $r) => [
                'id'         => $r->id,
                'created_at' => optional($r->created_at)->format('d.m.Y H:i:s'),
                'user'       => $r->user ? [
                    'id'    => $r->user->id,
                    'name'  => $r->user->name,
                    'email' => $r->user->email,
                ] : null,
                'note'       => $r->note,
                'data'       => $r->data,
            ]);

        return Inertia::render('Revisions/Index', [
            'title'    => 'История блока: ' . ($block->block_key ?? '#' . $block->id),
            'resource' => 'blocks',
            'record'   => [
                'id'       => $block->getKey(),
                'label'    => $block->translate('title', 'ru') ?? $block->title ?? $block->block_key,
                'edit_url' => route('admin.blocks.edit', ['id' => $block->getKey()]),
            ],
            'revisions' => $revisions,
        ]);
    }

    public function restoreForPageBlock(Request $request, int $id, int $revisionId): RedirectResponse
    {
        $block = PageBlock::findOrFail($id);
        $ok = $block->restoreRevision($revisionId);
        if (!$ok) {
            return back()->withErrors(['revision' => 'Не удалось восстановить']);
        }
        return redirect()
            ->route('admin.blocks.revisions.index', ['id' => $id])
            ->with('success', 'Ревизия восстановлена.');
    }
}
