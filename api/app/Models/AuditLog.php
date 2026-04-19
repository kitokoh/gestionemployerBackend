<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'platform';
    
    protected $table = 'audit_logs';

    public $timestamps = false; // Manually handled by AuditLogger

    protected $fillable = [
        'actor_type',
        'actor_id',
        'company_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship with company (if specified)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
