<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Camera extends Model
{
    use BelongsToCompany;
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
        'company_id',
        'name',
        'rtsp_url',
        'location',
        'is_active',
        'created_by',
        'metadata',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rtsp_url' => 'encrypted',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Obtient la compagnie à laquelle appartient la caméra.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Obtient les jetons d'accès associés à cette caméra.
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(CameraAccessToken::class);
    }

    /**
     * Obtient les permissions spécifiques des employés pour cette caméra.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(CameraPermission::class);
    }
}
