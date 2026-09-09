<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Contracts\HasApiTokens;

/**
 * Issue #7009 — quota de tokens Sanctum actifs par utilisateur.
 *
 * Chaque POST /auth/login (ou succès de challenge 2FA) crée un token sans
 * purge : les comptes démo accumulaient des centaines de tokens valides
 * (constaté DEV : 631 tokens sur le compte principal, liste
 * /api/v1/api-tokens inutilisable, surface d'attaque élargie sur des comptes
 * au mot de passe public). Ce service conserve les N tokens les plus récents
 * (`config('auth.max_active_tokens_per_user')`, défaut 10) et purge les plus
 * anciens — appelé immédiatement après la création d'un token de login
 * (AuthService::login et TwoFactorAuthService::verifyChallenge).
 *
 * 0 ou négatif = purge désactivée. La rotation (RefreshTokenAction) supprime
 * l'ancien token : elle ne passe pas par ce quota (déjà bornée 1 pour 1).
 */
final class TokenQuotaService
{
    public function prune(HasApiTokens $user): int
    {
        $quota = (int) config('auth.max_active_tokens_per_user', 10);
        if ($quota <= 0) {
            return 0;
        }

        // Les tokens les plus récents sont conservés (id croissant = création
        // chronologique). On purge tout ce qui dépasse le quota.
        $keptIds = $user->tokens()
            ->orderByDesc('id')
            ->limit($quota)
            ->pluck('id');

        $staleQuery = $user->tokens()->whereNotIn('id', $keptIds);
        $count = $staleQuery->count();

        if ($count > 0) {
            $staleQuery->delete();

            Log::channel('structured')->info('auth.tokens_pruned', [
                // Le contrat HasApiTokens n'expose pas d'identifiant — on
                // loggue la clé primaire quand le tokenable est un Eloquent.
                'tokenable_id' => $user instanceof Model ? $user->getKey() : null,
                'purged' => $count,
                'quota' => $quota,
            ]);
        }

        return $count;
    }
}
