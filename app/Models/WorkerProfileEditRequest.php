<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerProfileEditRequest extends Model
{
    protected $fillable = [
        'worker_id',
        'data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
