<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $table = 'forms';

    protected $fillable = [
        'name',
        'slug',
        'fields',
        'notify_email',
        'success_message',
        'is_active',
    ];

    protected $casts = [
        'fields'    => 'array',
        'is_active' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class)->latest('id');
    }

    /**
     * Laravel validation rules derived from the fields schema.
     * Unknown types fall back to string.
     */
    public function validationRules(): array
    {
        $rules = [];
        foreach ((array) $this->fields as $f) {
            $name = $f['name'] ?? null;
            if (!$name) continue;

            $rule = $f['required'] ?? false ? 'required' : 'nullable';
            switch ($f['type'] ?? 'text') {
                case 'email':    $rule .= '|email|max:255'; break;
                case 'url':      $rule .= '|url|max:500';   break;
                case 'tel':      $rule .= '|string|max:50'; break;
                case 'number':   $rule .= '|numeric';       break;
                case 'date':     $rule .= '|date';          break;
                case 'textarea': $rule .= '|string|max:10000'; break;
                case 'select':
                case 'radio':    $rule .= '|string|max:255';
                                 if (!empty($f['options'])) {
                                     $vals = array_map(
                                         fn ($o) => is_array($o) ? ($o['value'] ?? $o['label'] ?? '') : (string) $o,
                                         $f['options'],
                                     );
                                     $rule .= '|in:' . implode(',', $vals);
                                 }
                                 break;
                case 'checkbox': $rule .= '|boolean'; break;
                default:         $rule .= '|string|max:500';
            }
            $rules[$name] = $rule;
        }
        return $rules;
    }
}
