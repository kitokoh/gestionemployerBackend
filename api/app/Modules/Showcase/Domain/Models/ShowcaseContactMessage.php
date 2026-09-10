<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Message reçu via le formulaire de contact public d'une vitrine (BC-27
 * SHOWCASE, #6875 V-RGPD).
 *
 * Minimisation RGPD : seuls le nom, l'e-mail et le message du visiteur sont
 * stockés, avec l'horodatage du consentement explicite, une empreinte IP
 * hachée (anti-abus, jamais l'IP en clair) et une date de rétention bornée.
 * Aucune donnée RH/tenant interne. Tenant-scoped (`company_id`, sans FK —
 * conventions migrations tenant §2.6) ; `showcase_id` référence la vitrine.
 *
 * @property int $id
 * @property string $company_id
 * @property int $showcase_id
 * @property string $name
 * @property string $email
 * @property string $message
 * @property Carbon $consent_at
 * @property Carbon $retention_until
 * @property string|null $ip_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class ShowcaseContactMessage extends Model
{
    use BelongsToCompany;

    protected $table = 'showcase_contact_messages';

    protected $fillable = [
        'company_id',
        'showcase_id',
        'name',
        'email',
        'message',
        'consent_at',
        'retention_until',
        'ip_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'retention_until' => 'date',
        ];
    }
}
