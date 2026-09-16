<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = [
        'ticket_id',
        'status',
        'severity',
        'findings',
        'result',
        'error',
        'score',
        'grade',
    ];

    protected $casts = [
        'findings' => 'array',
        'result' => 'array',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Finding titles, used to compare two scans of the same ticket.
     */
    public function findingTitles(): array
    {
        return array_column($this->findings ?? [], 'title');
    }
}
