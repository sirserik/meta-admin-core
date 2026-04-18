<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\Lead;

class LeadController extends Controller
{
    protected const STATUSES = [
        ['value' => 'new',       'label' => 'Новая',           'color' => 'blue'],
        ['value' => 'contacted', 'label' => 'Связались',       'color' => 'indigo'],
        ['value' => 'qualified', 'label' => 'Квалифицирована', 'color' => 'purple'],
        ['value' => 'converted', 'label' => 'Преобразована',   'color' => 'green'],
        ['value' => 'rejected',  'label' => 'Отклонена',       'color' => 'gray'],
    ];

    protected const TYPES = [
        ['value' => 'application',  'label' => 'Поступление'],
        ['value' => 'consultation', 'label' => 'Консультация'],
    ];

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'type']);

        $query = Lead::query()->latest();
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type']))   $query->where('type',   $filters['type']);
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name',       'like', "%{$term}%")
                  ->orWhere('email',    'like', "%{$term}%")
                  ->orWhere('phone',    'like', "%{$term}%")
                  ->orWhere('first_name','like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate(20)->withQueryString();
        $paginator->getCollection()->transform(fn (Lead $l) => $this->presentRow($l));

        return Inertia::render('Leads/Index', [
            'title'    => 'Заявки',
            'items'    => $paginator,
            'statuses' => self::STATUSES,
            'types'    => self::TYPES,
            'filters'  => $filters,
            'counts'   => [
                'total'     => Lead::count(),
                'new'       => Lead::where('status', Lead::STATUS_NEW)->count(),
                'converted' => Lead::where('status', Lead::STATUS_CONVERTED)->count(),
            ],
        ]);
    }

    public function show(int $id): Response
    {
        $lead = Lead::findOrFail($id);
        return Inertia::render('Leads/Show', [
            'title'    => 'Заявка #' . $lead->id,
            'lead'     => $this->presentDetail($lead),
            'statuses' => self::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:new,contacted,qualified,converted,rejected',
        ]);
        $lead->update(['status' => $data['status']]);

        return back()->with('success', 'Статус изменён');
    }

    public function destroy(int $id): RedirectResponse
    {
        Lead::findOrFail($id)->delete();
        return redirect()
            ->route('admin.leads.index')
            ->with('success', 'Заявка удалена');
    }

    protected function presentRow(Lead $l): array
    {
        return [
            'id'          => $l->id,
            'name'        => $l->full_name,
            'email'       => $l->email,
            'phone'       => $l->phone,
            'type'        => $l->type,
            'program'     => $l->program_name,
            'status'      => $l->status,
            'status_name' => $l->status_name,
            'created_at'  => optional($l->created_at)->format('d.m.Y H:i'),
        ];
    }

    protected function presentDetail(Lead $l): array
    {
        return array_merge($this->presentRow($l), [
            'first_name' => $l->first_name,
            'last_name'  => $l->last_name,
            'year'       => $l->year,
            'message'    => $l->message,
            'interests'  => $l->interests,
            'call_time'  => $l->call_time,
            'questions'  => $l->questions,
            'source'     => $l->source,
            'ip_address' => $l->ip_address,
            'user_agent' => $l->user_agent,
        ]);
    }
}
