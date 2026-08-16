<?php

namespace Tests\Unit;

use App\Http\Requests\StoreUserCollectionRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\MessageResource;
use App\Models\User;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
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
            'name'                  => 'sh!@#invalid',
            'email'                 => 'invalid-email-pattern',
            'password'              => 'short',
            'password_confirmation' => 'mismatch',
        ];
        $invalidValidator = Validator::make($invalidPayload, $request->rules());
        $this->assertFalse($invalidValidator->passes());

        // 2. Assert valid input passes constraints
        $validPayload = [
            'name'                  => 'shohid_dev',
            'email'                 => 'shohid@backend.io',
            'password'              => 'StrictSecurePasswordPattern159!',
            'password_confirmation' => 'StrictSecurePasswordPattern159!',
            'first_name'            => 'Shohidullah',
            'last_name'             => 'Developer',
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
            'name'       => 'shohid_dev',
            'email'      => 'shohid@backend.io',
            'first_name' => 'Shohidullah',
            'last_name'  => 'Developer',
            'created_at' => now()->toIso8601String(),
        ]);

        $resource = (new UserResource($user))->resolve();

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

    /**
     * --- Test Message validation, model persistence, and resource transformation constraints ---
     */

    public function test_message_model_and_resource_constraints(): void
    {
        $request = new StoreMessageRequest();

        // 1. Assert invalid input fails request validation rules
        $invalidPayload = [
            'sender_id' => '',
            'message'   => '',
        ];
        $invalidValidator = Validator::make($invalidPayload, $request->rules());
        $this->assertFalse($invalidValidator->passes());
        $this->assertArrayHasKey('sender_id', $invalidValidator->errors()->toArray());
        $this->assertArrayHasKey('message', $invalidValidator->errors()->toArray());

        // 2. Assert valid input passes request validation rules
        $validPayload = [
            'sender_id' => 101,
            'auth_id'   => 101,
            'message'   => 'Automated test suite message payload verification.',
        ];
        $validValidator = Validator::make($validPayload, $request->rules());
        $this->assertTrue($validValidator->passes());

        // 3. Test Message database persistence via Eloquent
        $message = Message::create($validPayload);
        $this->assertNotNull($message->id);
        $this->assertEquals(101, $message->sender_id);
        $this->assertEquals(101, $message->auth_id);

        // 4. Test MessageResource data transformation output
        $resource = (new MessageResource($message))->resolve();
        $this->assertEquals(101, $resource['sender_id']);
        $this->assertEquals(101, $resource['auth_id']);
        $this->assertEquals('Automated test suite message payload verification.', $resource['message']);
        $this->assertIsInt($resource['id']);
        $this->assertIsString($resource['created_at']);
    }
}