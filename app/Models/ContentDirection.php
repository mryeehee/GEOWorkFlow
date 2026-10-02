<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDirection extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'inputs_json',
        'suggestions_json',
        'answer_text',
        'error_message',
        'created_by_admin_id',
        'ai_visibility_run_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'inputs_json' => 'array',
            'suggestions_json' => 'array',
            'created_by_admin_id' => 'integer',
            'ai_visibility_run_id' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function visibilityRun(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityRun::class, 'ai_visibility_run_id');
    }
}
