<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Message;
use App\Http\Resources\MessageResource;
use App\Http\Requests\StoreMessageRequest;

class ChatController extends Controller
{
    /**
     * Store and broadcast a new message.
     */
    public function sendMessage(StoreMessageRequest $request)
    {
        $validated = $request->validated();

        // 1. Durability: Save permanently to MySQL database via Eloquent
        $message = Message::create($validated);
        $payload = (new MessageResource($message))->resolve();
        $jsonPayload = json_encode($payload);

        // 2. Real-Time Broadcasting: Publish live event to Redis channel
        Redis::publish('chat-channel', $jsonPayload);

        // 3. Store latest pointers for fast cache-based reference
        Redis::setex('latest_chat_id', 3600, $message->id);
        Redis::setex('latest_chat_message', 3600, $jsonPayload);

        return response()->json([
            'status' => 'success',
            'data' => $payload
        ], 201);
    }

    /**
     * Production-Ready SSE Stream Route (Kubernetes Native / No Nginx):
     * - 30-second maximum connection lifetime recycling window
     * - MySQL offline catch-up backfilling for missed/seeded messages (defaults to 0 if after_id omitted)
     * - Robust polling loop with incremental database backfilling for multi-message gaps (without pings)
     */
    public function stream(Request $request)
    {
        set_time_limit(0);

        $afterIdParam = $request->query('after_id', null);

        // Default to 0 if after_id is omitted so offline history / seeded messages are fetched
        try {
            $lastSentId = $afterIdParam !== null ? (int) $afterIdParam : 0;
        } catch (\Exception $e) {
            $lastSentId = 0;
        }

        // Offline Catch-Up: Fetch missed messages from MySQL starting from $lastSentId
        $missedMessages = [];
        try {
            $newMessages = Message::where('id', '>', $lastSentId)->orderBy('id')->get();
            if ($newMessages->isNotEmpty()) {
                $missedMessages = MessageResource::collection($newMessages)->resolve();
                $lastSentId = $newMessages->last()->id;
            }
        } catch (\Exception $e) {
            // Fallback or log if query fails
        }

        return response()->stream(function () use (&$lastSentId, $missedMessages) {
            $startTime = time();
            $maxDuration = 30; // 30-second connection lifetime recycling window

            // Connection success handshake
            echo "data: " . json_encode(['message' => 'Connected to SSE stream successfully']) . "\n\n";
            @flush();

            // Deliver any missed/seeded historical messages from MySQL catch-up first
            foreach ($missedMessages as $msg) {
                echo "data: " . json_encode($msg) . "\n\n";
                @flush();
            }

            try {
                while (true) {
                    if ((time() - $startTime) > $maxDuration) {
                        break; // Gracefully close connection after 30 seconds for pod/worker recycling
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
                        }
                    }

                    usleep(500000); // 0.5s pause to maintain non-blocking execution efficiency
                }
            } catch (\Exception $e) {
                // Graceful cleanup on client drop or exception
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Fallback historical fetch endpoint backed directly by MySQL.
     * Defaults to ID 0 if after_id is omitted to ensure seeded messages are returned.
     */
    public function fetchMessages(Request $request)
    {
        $afterIdParam = $request->query('after_id', null);

        if ($afterIdParam === null) {
            $afterId = 0;
        } else {
            try {
                $afterId = (int) $afterIdParam;
            } catch (\Exception $e) {
                $afterId = 0;
            }
        }

        $messages = Message::where('id', '>', $afterId)->orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'data' => MessageResource::collection($messages)
        ], 200);
    }
}