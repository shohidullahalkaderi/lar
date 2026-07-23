<?php

namespace Tests\Unit;

use App\Http\Requests\StoreUserCollectionRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis; // Added Redis Facade
use Tests\TestCase;

class AuthenticationUnitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation constraints inside StoreUserCollectionRequest.
     */
    public function test_store_user_collection_request_validation_rules(): void
    {
        $request = new StoreUserCollectionRequest();

        // 1. Assert invalid input fails constraints
        $invalidPayload = [
            'name'     => 'sh!@#invalid',
            'email'    => 'invalid-email-pattern',
            'password' => 'short',
        ];
        $invalidValidator = Validator::make($invalidPayload, $request->rules());
        $this->assertFalse($invalidValidator->passes());

        // 2. Assert valid input passes constraints
        $validPayload = [
            'name'       => 'shohid_dev',
            'email'      => 'shohid@backend.io',
            'password'   => 'StrictSecurePasswordPattern159!',
            'first_name' => 'Shohidullah',
            'last_name'  => 'Developer',
        ];
        $validValidator = Validator::make($validPayload, $request->rules());
        $this->assertTrue($validValidator->passes());
    }

    /**
     * Test UserResource structural data transformations.
     */
    public function test_user_resource_data_transformation(): void
    {
        $user = new User();
        $user->setRawAttributes([
            'id'         => 1,
            'name'       => 'shohid_dev',
            'email'      => 'shohid@backend.io',
            'first_name' => 'Shohidullah',
            'last_name'  => 'Developer',
            'created_at' => now()->toIso8601String(),
        ]);

        $resource = (new UserResource($user))->resolve();

        $this->assertEquals(1, $resource['id']);
        $this->assertEquals('shohid_dev', $resource['name']);
        $this->assertEquals('shohid@backend.io', $resource['email']);
        $this->assertEquals('Shohidullah', $resource['first_name']);
        $this->assertEquals('Developer', $resource['last_name']);
        $this->assertIsString($resource['created_at']);
    }

    /**
     * Test live Redis connection infrastructure.
     */
    public function test_redis_infrastructure_connection(): void
    {
        $testKey = 'infra_check_' . uniqid();
        $testValue = 'laravel_redis_operational';

        // 1. Write connection payload to Redis
        Redis::set($testKey, $testValue);

        // 2. Read back data asset and assert structural match
        $retrievedValue = Redis::get($testKey);
        $this->assertEquals($testValue, $retrievedValue);

        // 3. Destruct and assert clean state post-execution
        Redis::del($testKey);
        $this->assertNull(Redis::get($testKey));
    }
}