<?php

namespace Tests\Unit;

use App\Http\Requests\StoreUserCollectionRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Inline Stub representing TokenManager behavior until implementation is ready.
 */
class TokenManager
{
    public static function createSession(int $userId): string
    {
        $token = Str::random(40);
        Cache::put("user_session_{$token}", $userId, 600);
        return $token;
    }

    public static function getUserId(string $token): ?int
    {
        return Cache::get("user_session_{$token}");
    }

    public static function destroySession(string $token): void
    {
        Cache::forget("user_session_{$token}");
    }
}

class AuthenticationUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Isolate unit cache memory state
    }

    /**
     * Test validation constraints inside StoreUserCollectionRequest.
     */
    public function test_store_user_collection_request_validation_rules(): void
    {
        $request = new StoreUserCollectionRequest();

        // 1. Invalid payload case (Violates string rules, short password, bad email)
        $invalidPayload = [
            'name'       => 'sh!@#invalid', // Swapped from username -> name
            'email'      => 'invalid-email-pattern',
            'password'   => 'short',
        ];

        $invalidValidator = Validator::make($invalidPayload, $request->rules());
        $this->assertFalse($invalidValidator->passes());
        $this->assertArrayHasKey('name', $invalidValidator->errors()->toArray());
        $this->assertArrayHasKey('email', $invalidValidator->errors()->toArray());
        $this->assertArrayHasKey('password', $invalidValidator->errors()->toArray());

        // 2. Valid payload case
        $validPayload = [
            'name'       => 'shohid_dev', // Swapped from username -> name
            'email'      => 'shohid@backend.io',
            'password'   => 'StrictSecurePasswordPattern159!',
            'first_name' => 'Shohidullah',
            'last_name'  => 'Developer',
        ];

        $validValidator = Validator::make($validPayload, $request->rules());
        $this->assertTrue($validValidator->passes());
    }

    /**
     * Test TokenManager lifecycle operations natively inside Redis cache memory.
     */
    public function test_token_manager_session_lifecycle(): void
    {
        $userId = 99;

        // 1. Test Session Creation
        $token = TokenManager::createSession($userId);
        $this->assertIsString($token);
        $this->assertEquals($userId, TokenManager::getUserId($token));

        // 2. Test Session Destruction (Eviction from Redis)
        TokenManager::destroySession($token);
        $this->assertNull(TokenManager::getUserId($token));
    }

    /**
     * Test UserResource structural data transformations.
     */
    /**
     * Test UserResource structural data transformations.
     */
    public function test_user_resource_data_transformation(): void
    {
        // 1. Force state mapping using an explicit attributes array array block
        $user = new User();
        $user->setRawAttributes([
            'id'         => 1,
            'name'       => 'shohid_dev',
            'email'      => 'shohid@backend.io',
            'first_name' => 'Shohidullah',
            'last_name'  => 'Developer',
            'created_at' => now()->toIso8601String(),
        ]);

        // 2. Execute via standard resource collection extraction methods
        $resource = (new UserResource($user))->resolve();

        // 3. Strict structural assertions
        $this->assertEquals(1, $resource['id']);
        $this->assertEquals('shohid_dev', $resource['name']);
        $this->assertEquals('shohid@backend.io', $resource['email']);
        $this->assertEquals('Shohidullah', $resource['first_name']);
        $this->assertEquals('Developer', $resource['last_name']);
        $this->assertIsString($resource['created_at']);
    }
}