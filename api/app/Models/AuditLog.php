<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditLog extends Model
{
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

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.audit_logs' : 'audit_logs';
    }

    /**
     * Relationship with company (if specified)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
