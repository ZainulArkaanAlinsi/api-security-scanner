<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'api_url',
        'status',
        'severity',
        'findings',
        'scan_result',
        'scanned_at',
        'auto_scan',
        'score',
        'grade',
        'share_token',
    ];

    protected $casts = [
        'findings' => 'array',
        'scan_result' => 'array',
        'scanned_at' => 'datetime',
        'auto_scan' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scans()
    {
        return $this->hasMany(Scan::class);
    }
}
