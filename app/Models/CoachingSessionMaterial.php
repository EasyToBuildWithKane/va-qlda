<?php

namespace App\Models;

use App\Support\Enums\CoachingMaterialType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachingSessionMaterial extends Model
{
    public $timestamps = false;

    protected $table = 'coaching_session_materials';

    protected $fillable = [
        'session_id',
        'type',
        'title',
        'url',
        'path',
        'mime_type',
        'size',
        'sort_order',
        'created_at',
    ];

    protected $casts = [
        'type' => CoachingMaterialType::class,
        'size' => 'integer',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }
}
