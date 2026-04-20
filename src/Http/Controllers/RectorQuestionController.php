<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\RectorQuestion;

class RectorQuestionController extends Controller
{
    protected const STATUSES = [
        ['value' => 'new',       'label' => 'Новый',           'color' => 'blue'],
        ['value' => 'in_review', 'label' => 'На рассмотрении', 'color' => 'amber'],
        ['value' => 'answered',  'label' => 'Отвечено',        'color' => 'green'],
        ['value' => 'rejected',  'label' => 'Отклонено',       'color' => 'gray'],
    ];

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'category']);
        $query = RectorQuestion::query()->latest();

        if (!empty($filters['status']))   $query->where('status', $filters['status']);
        if (!empty($filters['category'])) $query->where('category', $filters['category']);
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('subject', 'like', "%{$term}%")
                  ->orWhere('question', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate(20)->withQueryString();
        $paginator->getCollection()->transform(fn (RectorQuestion $q) => $this->presentRow($q));

        return Inertia::render('RectorQuestions/Index', [
            'title'      => 'Вопросы ректору',
            'items'      => $paginator,
            'statuses'   => self::STATUSES,
            'categories' => $this->categoriesList(),
            'filters'    => $filters,
            'counts'     => [
                'total'    => RectorQuestion::count(),
                'new'      => RectorQuestion::where('status', RectorQuestion::STATUS_NEW)->count(),
                'answered' => RectorQuestion::where('status', RectorQuestion::STATUS_ANSWERED)->count(),
            ],
        ]);
    }

    public function show(RectorQuestion $rectorQuestion): Response
    {
        return Inertia::render('RectorQuestions/Show', [
            'title'      => 'Вопрос #' . $rectorQuestion->id,
            'question'   => $this->presentDetail($rectorQuestion),
            'statuses'   => self::STATUSES,
            'categories' => $this->categoriesList(),
        ]);
    }

    public function update(Request $request, RectorQuestion $rectorQuestion): RedirectResponse
    {
        $data = $request->validate([
            'answer'       => 'nullable|string',
            'status'       => 'required|in:new,in_review,answered,rejected',
            'is_published' => 'nullable|boolean',
        ]);

        $updates = [
            'answer'       => $data['answer'] ?? null,
            'status'       => $data['status'],
            'is_published' => $request->boolean('is_published'),
        ];

        if ($data['status'] === RectorQuestion::STATUS_ANSWERED && !$rectorQuestion->answered_at) {
            $updates['answered_at'] = now();
        }

        $rectorQuestion->update($updates);

        return redirect()
            ->route('admin.rector-questions.show', $rectorQuestion)
            ->with('success', 'Сохранено');
    }

    public function destroy(RectorQuestion $rectorQuestion): RedirectResponse
    {
        $rectorQuestion->delete();
        return redirect()
            ->route('admin.rector-questions.index')
            ->with('success', 'Вопрос удалён');
    }

    protected function categoriesList(): array
    {
        return collect(RectorQuestion::CATEGORIES)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    protected function presentRow(RectorQuestion $q): array
    {
        return [
            'id'             => $q->id,
            'name'           => $q->full_name,
            'email'          => $q->email,
            'category'       => $q->category,
            'category_label' => $q->category_label,
            'subject'        => $q->subject,
            'status'         => $q->status,
            'is_published'   => (bool) $q->is_published,
            'has_answer'     => !empty($q->answer),
            'created_at'     => optional($q->created_at)->format('d.m.Y H:i'),
        ];
    }

    protected function presentDetail(RectorQuestion $q): array
    {
        return array_merge($this->presentRow($q), [
            'first_name'  => $q->first_name,
            'last_name'   => $q->last_name,
            'question'    => $q->question,
            'answer'      => $q->answer,
            'answered_at' => optional($q->answered_at)->format('Y-m-d\TH:i'),
            'ip_address'  => $q->ip_address,
        ]);
    }
}
