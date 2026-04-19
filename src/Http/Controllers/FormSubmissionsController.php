<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\Form;
use Meta\AdminCore\Models\FormSubmission;

/**
 * Two concerns in one controller:
 *
 *   1) Admin-side listing + export of submissions for a given form.
 *   2) Public-side submit endpoint (POST /api/forms/{slug}) with
 *      validation derived from the form's fields schema.
 */
class FormSubmissionsController extends Controller
{
    /* -------------------------------------------------------------- */
    /*  Admin                                                          */
    /* -------------------------------------------------------------- */

    public function index(int $formId): Response
    {
        $form = Form::findOrFail($formId);
        $submissions = $form->submissions()
            ->limit(500)
            ->get()
            ->map(fn (FormSubmission $s) => [
                'id'         => $s->id,
                'status'     => $s->status,
                'data'       => $s->data,
                'ip_address' => $s->ip_address,
                'created_at' => optional($s->created_at)->format('d.m.Y H:i:s'),
            ]);

        return Inertia::render('Forms/Submissions', [
            'title' => 'Заявки: ' . $form->name,
            'form'  => [
                'id'     => $form->id,
                'name'   => $form->name,
                'slug'   => $form->slug,
                'fields' => (array) $form->fields,
            ],
            'submissions' => $submissions,
        ]);
    }

    public function setStatus(Request $request, int $formId, int $submissionId): RedirectResponse
    {
        $request->validate(['status' => 'required|in:new,read,replied,spam']);
        $sub = FormSubmission::where('form_id', $formId)->findOrFail($submissionId);
        $sub->update(['status' => $request->input('status')]);
        return back();
    }

    public function destroy(int $formId, int $submissionId): RedirectResponse
    {
        FormSubmission::where('form_id', $formId)->findOrFail($submissionId)->delete();
        return back()->with('success', 'Заявка удалена');
    }

    public function export(int $formId)
    {
        $form = Form::findOrFail($formId);
        $fieldNames = array_column((array) $form->fields, 'name');

        return response()->streamDownload(function () use ($form, $fieldNames) {
            $h = fopen('php://output', 'w');
            // UTF-8 BOM so Excel picks up cyrillics correctly.
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, array_merge(['#', 'Когда', 'Статус', 'IP'], $fieldNames));
            foreach ($form->submissions()->cursor() as $s) {
                $row = [$s->id, optional($s->created_at)->format('Y-m-d H:i:s'), $s->status, $s->ip_address];
                foreach ($fieldNames as $k) {
                    $v = $s->data[$k] ?? '';
                    $row[] = is_array($v) ? implode(', ', $v) : (string) $v;
                }
                fputcsv($h, $row);
            }
            fclose($h);
        }, 'form-' . $form->slug . '-submissions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* -------------------------------------------------------------- */
    /*  Public submit endpoint                                         */
    /* -------------------------------------------------------------- */

    public function submit(Request $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->first();
        if (!$form) return response()->json(['message' => 'Form not found'], 404);

        $rules = $form->validationRules();
        $data  = $rules ? $request->validate($rules) : $request->all();

        $submission = FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => $data,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'status'     => 'new',
        ]);

        // Optional notify email — best-effort, doesn't block the response.
        if ($form->notify_email) {
            try {
                Mail::raw(
                    "Новая заявка через форму «{$form->name}»:\n\n" . print_r($data, true),
                    function ($m) use ($form) {
                        $m->to($form->notify_email)->subject("[{$form->name}] Новая заявка");
                    },
                );
            } catch (\Throwable $e) { /* silently swallow — mail misconfig shouldn't block submit */ }
        }

        return response()->json([
            'ok'      => true,
            'id'      => $submission->id,
            'message' => $form->success_message ?: 'Спасибо! Заявка принята.',
        ], 201);
    }
}
