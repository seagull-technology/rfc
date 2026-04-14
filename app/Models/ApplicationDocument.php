<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'uploaded_by_user_id',
        'reviewed_by_user_id',
        'document_type',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'status',
        'note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function localizedStatus(): string
    {
        return __('app.documents.statuses.'.Str::lower($this->status ?: 'submitted'));
    }

    public function localizedType(): string
    {
        return __('app.documents.types.'.Str::lower($this->document_type ?: 'other'));
    }
}
