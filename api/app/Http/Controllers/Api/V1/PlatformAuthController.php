<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PlatformAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::query()->where('email', $validated['email'])->first();

        if (! $superAdmin || ! Hash::check($validated['password'], $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        $token = $superAdmin->createToken($validated['device_name'] ?? 'platform-api')->plainTextToken;

        AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.auth.login', $request, [
            'email' => $superAdmin->email,
            'device_name' => $validated['device_name'] ?? 'platform-api',
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.auth.me', $request);

        return new JsonResponse([
            'data' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = $request->user('super_admin_api');
        if ($superAdmin) {
            AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.auth.logout', $request);
        }

        $request->user('super_admin_api')?->currentAccessToken()?->delete();

        return new JsonResponse(['status' => 'ok']);
    }
}
