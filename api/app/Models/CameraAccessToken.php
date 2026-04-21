<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraAccessToken extends Model
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
        'token',
        'expires_at',
        'metadata',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Obtient la caméra associée à ce jeton.
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }
}
