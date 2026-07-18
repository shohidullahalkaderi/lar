<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserCollectionRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    private const CACHE_TTL = 86400; // 24 Hours tracking retention

    public function register(StoreUserCollectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'first_name' => $validated['first_name'] ?? null,
            'last_name'  => $validated['last_name'] ?? null,
        ]);

        $tokenResult = $user->createToken('auth_token');
        
        // Cache user model payload in Redis using the token identifier
        Cache::put("user_session_{$tokenResult->plainTextToken}", $user, self::CACHE_TTL);

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $tokenResult->plainTextToken
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'name'     => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt(['name' => $credentials['name'], 'password' => $credentials['password']])) {
            return response()->json([
                'message' => 'Invalid structural credentials provided.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();
        $tokenResult = $user->createToken('auth_token');

        // Prime the high-performance Redis read cache layer
        Cache::put("user_session_{$tokenResult->plainTextToken}", $user, self::CACHE_TTL);

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $tokenResult->plainTextToken
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $request->bearerToken();

        // Evict token cache mapping instantly from Redis
        if ($currentToken) {
            Cache::forget("user_session_{$currentToken}");
        }

        // Revoke token record from database storage
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out from active session.'
        ], Response::HTTP_OK);
    }
}