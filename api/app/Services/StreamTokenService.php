<?php

namespace App\Services;

use App\Models\Camera;
use App\Models\CameraAccessToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StreamTokenService
{
    /**
     * Génère un jeton d'accès pour une caméra spécifique.
     *
     * @param Camera $camera
     * @param int $expiresInMinutes
     * @return string
     */
    public function generateToken(Camera $camera, int $expiresInMinutes = 60): string
    {
        $token = Str::random(64);

        CameraAccessToken::create([
            'camera_id' => $camera->id,
            'token' => $token,
            'expires_at' => Carbon::now()->addMinutes($expiresInMinutes),
            'metadata' => [
                'generated_at' => Carbon::now()->toIso8601String(),
            ]
        ]);

        return $token;
    }

    /**
     * Valide un jeton pour une caméra donnée.
     *
     * @param string $token
     * @param int|null $cameraId
     * @return bool
     */
    public function validateToken(string $token, ?int $cameraId = null): bool
    {
        $query = CameraAccessToken::where('token', $token)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', Carbon::now());
            });

        if ($cameraId) {
            $query->where('camera_id', $cameraId);
        }

        return $query->exists();
    }

    /**
     * Révoque tous les jetons d'une caméra (utile en cas de faille).
     *
     * @param Camera $camera
     * @return int
     */
    public function revokeAllForCamera(Camera $camera): int
    {
        return CameraAccessToken::where('camera_id', $camera->id)->delete();
    }
}
