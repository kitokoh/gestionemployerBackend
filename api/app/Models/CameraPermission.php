<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraPermission extends Model
{
    use HasFactory;

    /**
     * La connexion à la base de données utilisée par le modèle.
     *
     * @var string
     */
    protected $connection = 'platform';

    /**
     * Les attributs qui sont assignables.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'camera_id',
        'employee_id',
        'permission_level',
    ];

    /**
     * Obtient la caméra associée à cette permission.
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * Obtient l'employé associé à cette permission.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
