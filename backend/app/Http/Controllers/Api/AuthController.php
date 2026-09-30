<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): ApiResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->where('email', $data['email'])
            ->first();

        // Always perform a password comparison to reduce account-enumeration
        // timing differences. This fallback is a valid bcrypt hash.
        $hash = $user?->password
            ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

        $passwordMatches = Hash::check($data['password'], $hash);

        abort_unless(
            $user !== null && $user->is_active && $passwordMatches,
            401
        );

        if (Hash::needsRehash($user->password)) {
            $user->forceFill([
                'password' => Hash::make($data['password']),
            ])->save();
        }

        $expirationMinutes = (int) config('sanctum.expiration', 10080);

        $token = $user->createToken(
            'inquiry-portal',
            ['*'],
            $expirationMinutes > 0
                ? now()->addMinutes($expirationMinutes)
                : null
        );

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user->load('team')),
        ], 'You are now signed in.');
    }

    public function logout(Request $request): ApiResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'You have been signed out.');
    }

    public function me(Request $request): ApiResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load('team')),
            'Current user retrieved successfully.'
        );
    }
}
