<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeywordOpportunity extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'brand_keyword',
        'keyword',
        'score',
        'source_domain',
        'status',
        'analysis_json',
        'evidence_json',
        'imported_keyword_id',
        'imported_at',
        'dismissed_at',
        'last_mined_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'analysis_json' => 'array',
            'evidence_json' => 'array',
            'imported_keyword_id' => 'integer',
            'imported_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'last_mined_at' => 'datetime',
        ];
    }
}
