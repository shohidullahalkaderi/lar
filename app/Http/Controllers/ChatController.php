<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Message;
use App\Http\Resources\MessageResource;
use App\Http\Requests\StoreMessageRequest;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    /**
     * Store and broadcast a new message.
     */
    public function sendMessage(StoreMessageRequest $request)
    {
        try {
            $validated = $request->validated();
            
            // Ensure authenticated user ID is attached to the payload if not handled by request
            $validated['auth_id'] = $request->user()->id;

            // 1. Durability: Save permanently to MySQL database via Eloquent
            $message = Message::create($validated);
            $payload = (new MessageResource($message))->resolve();
            $jsonPayload = json_encode($payload);

            // 2. Real-Time Broadcasting: Publish live event to Redis channel
            Redis::publish('chat-channel', $jsonPayload);

            // 3. Store latest global pointers for fast cache-based reference
            Redis::setex('latest_chat_id', 3600, $message->id);
            Redis::setex('latest_chat_message', 3600, $jsonPayload);

            return response()->json([
                'detail' => $payload
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'detail' => 'Invalid message provided.'
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Production-Ready SSE Stream Route (Kubernetes Native / No Nginx):
     * - 30-second maximum connection lifetime recycling window
     * - Server-side stateful delta tracking per user with strict 0-fallback for seeded history
     * - Polling loop with incremental database backfilling for multi-message gaps
     */
    public function stream(Request $request)
    {
        set_time_limit(0);

        $user = $request->user();
        if (!$user) {
            return response()->json(
                ['detail' => 'Invalid auth token provided.'], 
                Response::HTTP_UNAUTHORIZED
            );
        }

        $redisCursorKey = "chat:user_last_seen:{$user->id}";
        $lastSeenIdStr = Redis::get($redisCursorKey);

        if ($lastSeenIdStr !== null) {
            $lastSentId = (int) $lastSeenIdStr;
        } else {
            // Force fallback to 0 on first connection.
            // Guarantees seeded database messages are backfilled in Kubernetes even if Redis keys reset.
            $lastSentId = 0;
        }

        // Server-Side Delta Query: Fetch only messages strictly greater than the user's last seen ID
        $missedMessages = [];
        try {
            $newMessages = Message::where('id', '>', $lastSentId)->orderBy('id')->get();
            if ($newMessages->isNotEmpty()) {
                $missedMessages = MessageResource::collection($newMessages)->resolve();
                $lastSentId = $newMessages->last()->id;
                // Update user cursor state in Redis
                Redis::set($redisCursorKey, $lastSentId);
            }
        } catch (\Exception $e) {
            // Log or fallback on database error
        }

        return response()->stream(function () use (&$lastSentId, $missedMessages, $redisCursorKey) {
            $startTime = time();
            $maxDuration = 30; // 30-second connection lifetime recycling window

            // Connection success handshake
            echo "data: " . json_encode(['detail' => 'Connected to SSE stream successfully']) . "\n\n";
            @flush();

            // Deliver server-side delta-queried missed messages first
            foreach ($missedMessages as $msg) {
                echo "data: " . json_encode($msg) . "\n\n";
                @flush();
            }

            try {
                while (true) {
                    if ((time() - $startTime) > $maxDuration) {
                        break; // Gracefully close connection after 30 seconds for worker recycling
                    }

                    if (connection_aborted()) {
                        break; // Terminate early if the client drops the connection
                    }

                    $latestId = Redis::get('latest_chat_id');

                    // If a new message ID exists beyond what we've processed, backfill and stream them
                    if ($latestId && (int) $latestId > $lastSentId) {
                        $interveningMessages = Message::where('id', '>', $lastSentId)
                            ->where('id', '<=', (int) $latestId)
                            ->orderBy('id')
                            ->get();

                        if ($interveningMessages->isNotEmpty()) {
                            foreach ($interveningMessages as $msg) {
                                $payload = (new MessageResource($msg))->resolve();
                                echo "data: " . json_encode($payload) . "\n\n";
                                @flush();
                            }
                            $lastSentId = (int) $latestId;
                            // Automatically advance user's cursor state in Redis
                            Redis::set($redisCursorKey, $lastSentId);
                        }
                    }

                    usleep(500000); // 0.5s pause to maintain non-blocking execution efficiency
                }
            } catch (\Exception $e) {
                echo "data: " . json_encode(['detail' => $e->getMessage()]) . "\n\n";
                @flush();
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Fallback historical fetch endpoint backed directly by MySQL.
     * Uses server-side Redis session tracking if after_id is omitted.
     */
    public function fetchMessages(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(
                ['detail' => 'Invalid auth token provided.'], 
                Response::HTTP_UNAUTHORIZED
            );
        }

        $afterIdParam = $request->query('after_id', null);

        if ($afterIdParam === null) {
            $redisCursorKey = "chat:user_last_seen:{$user->id}";
            $lastSeenIdStr = Redis::get($redisCursorKey);
            $afterId = $lastSeenIdStr !== null ? (int) $lastSeenIdStr : 0;
        } else {
            try {
                $afterId = (int) $afterIdParam;
            } catch (\Exception $e) {
                $afterId = 0;
            }
        }

        try {
            $messages = Message::where('id', '>', $afterId)->orderBy('id')->get();

            if ($messages->isNotEmpty()) {
                Redis::set("chat:user_last_seen:{$user->id}", $messages->last()->id);
            }

            return response()->json([
                'detail' => MessageResource::collection($messages)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}