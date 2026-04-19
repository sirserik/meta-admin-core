<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    protected $table = 'form_submissions';
    public $timestamps = false;

    protected $fillable = [
        'form_id',
        'data',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'data'       => 'array',
        'created_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
