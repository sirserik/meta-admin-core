<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\Form;

/**
 * Admin CRUD for the form builder. List / create / edit / delete a
 * form; the submissions list + export is handled by
 * {@see FormSubmissionsController}.
 */
class FormsController extends Controller
{
    public function index(): Response
    {
        $forms = Form::query()
            ->withCount('submissions')
            ->latest('id')
            ->get()
            ->map(fn (Form $f) => [
                'id'                => $f->id,
                'name'              => $f->name,
                'slug'              => $f->slug,
                'fields_count'      => count((array) $f->fields),
                'submissions_count' => (int) $f->submissions_count,
                'is_active'         => $f->is_active,
                'updated_at'        => optional($f->updated_at)->format('d.m.Y H:i'),
            ]);

        return Inertia::render('Forms/Index', [
            'title' => 'Формы',
            'forms' => $forms,
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm(new Form([
            'name'   => '',
            'slug'   => '',
            'fields' => [],
            'success_message' => 'Спасибо! Заявка принята.',
            'is_active' => true,
        ]), false);
    }

    public function edit(int $id): Response
    {
        return $this->renderForm(Form::findOrFail($id), true);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if (empty($data['slug'])) $data['slug'] = Str::slug($data['name']);

        $form = Form::create($data);
        return redirect()->route('admin.forms.edit', ['id' => $form->id])
            ->with('success', 'Форма создана');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $form = Form::findOrFail($id);
        $data = $this->validated($request);
        if (empty($data['slug'])) $data['slug'] = Str::slug($data['name']);
        $form->update($data);

        return back()->with('success', 'Форма обновлена');
    }

    public function destroy(int $id): RedirectResponse
    {
        Form::findOrFail($id)->delete();
        return redirect()->route('admin.forms.index')->with('success', 'Форма удалена');
    }

    protected function renderForm(Form $form, bool $isEdit): Response
    {
        return Inertia::render('Forms/Edit', [
            'title' => $isEdit ? ('Форма: ' . $form->name) : 'Новая форма',
            'item'  => [
                'id'              => $form->id,
                'name'            => $form->name,
                'slug'            => $form->slug,
                'fields'          => (array) $form->fields,
                'notify_email'    => $form->notify_email,
                'success_message' => $form->success_message,
                'is_active'       => (bool) $form->is_active,
            ],
            'isEdit'      => $isEdit,
            'fieldTypes'  => $this->availableFieldTypes(),
            'submit_url'  => $isEdit ? url('/api/forms/' . $form->slug) : null,
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:255',
            'slug'            => 'nullable|string|max:120',
            'fields'          => 'array',
            'fields.*.name'   => 'required|string|max:100',
            'fields.*.label'  => 'required|string|max:255',
            'fields.*.type'   => 'required|string|max:30',
            'notify_email'    => 'nullable|email|max:255',
            'success_message' => 'nullable|string|max:500',
            'is_active'       => 'nullable|boolean',
        ]);
    }

    protected function availableFieldTypes(): array
    {
        return [
            ['key' => 'text',     'label' => 'Текст (одна строка)'],
            ['key' => 'textarea', 'label' => 'Текст (многострочный)'],
            ['key' => 'email',    'label' => 'Email'],
            ['key' => 'tel',      'label' => 'Телефон'],
            ['key' => 'url',      'label' => 'URL'],
            ['key' => 'number',   'label' => 'Число'],
            ['key' => 'date',     'label' => 'Дата'],
            ['key' => 'select',   'label' => 'Выпадающий список'],
            ['key' => 'radio',    'label' => 'Радио-кнопки'],
            ['key' => 'checkbox', 'label' => 'Галочка'],
        ];
    }
}
