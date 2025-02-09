<?php

namespace App\Http\Controllers;

use App\Models\EventUser;
use App\Models\DomainEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPSSLConnection;
class ChatController extends Controller
{
    /**
     * Listen to RabbitMQ queue and display messages being published.
     */
   

    public function listenRabbitMQ()
    {
        $sslOptions = [
            'cafile'           => '/etc/rabbitmq/certs/ca_certificate.pem',
            'verify_peer'      => true,
            'verify_peer_name' => false
        ];
    
        $connection = new AMQPSSLConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5671),  // Use SSL port
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            '/',
            $sslOptions
        );
    
        $channel = $connection->channel();
        $channel->queue_declare(env('RABBITMQ_QUEUE', 'event_joined'), false, true, false, false);
        $channel->basic_qos(null, 1, null);
    
        $callback = function ($msg) {
            echo 'Message received: ', $msg->body, "\n";
            Log::info('Message received by RabbitMQ listener:', ['message' => $msg->body]);
    
            $messageData = json_decode($msg->body, true);
    
            EventUser::create([
                'event_id'   => $messageData['event_id'],
                'event_name' => $messageData['event_name'],
                'user_id'    => $messageData['user_id'],
                'user_name'  => $messageData['user_name'],
                'message'    => $messageData['message'],
            ]);
    
            $msg->ack();
        };
    
        $channel->basic_consume(env('RABBITMQ_QUEUE', 'event_joined'), '', false, false, false, false, $callback);
    
        while ($channel->is_consuming()) {
            $channel->wait();
        }
    
        $channel->close();
        $connection->close();
    }
    

    /**
     * Send a message in an event chat.
     */
    public function sendMessage(Request $request, $id)
    {
        try {
            // Validate the token and retrieve user info
            $token = $this->validateToken($request);
            $payload  = JWTAuth::setToken($token)->getPayload();
            $userId   = $payload->get('sub');
            $userName = $payload->get('name');

            // Validate the message input
            $validatedData = $request->validate([
                'message' => 'required|string',
            ]);

            // Check if the user is participating in the event
            $isParticipating = EventUser::where('event_id', $id)
                ->where('user_id', $userId)
                ->exists();

            if (!$isParticipating) {
                Log::warning('Unauthorized attempt to send a message to an event:', [
                    'user_id'  => $userId,
                    'event_id' => $id,
                ]);
                return response()->json(['error' => 'Unauthorized: User is not participating in this event'], 403);
            }

            // Optionally, store the message immediately in the chat table
            $messageData = [
                'event_id'   => $id,
                'event_name' => "Evento $id",
                'user_id'    => $userId,
                'user_name'  => $userName,
                'message'    => $validatedData['message'],
            ];
            EventUser::create($messageData);

            // Prepare the event payload (including participants for notification purposes)
            $eventPayload = [
                'event_id'     => $id,
                'event_name'   => "Evento $id",
                'user_id'      => $userId,
                'user_name'    => $userName,
                'message'      => $validatedData['message'],
                'timestamp'    => now()->toISOString(),
                // Retrieve all unique participants for the event:
                'participants' => EventUser::where('event_id', $id)
                                        ->distinct()
                                        ->get(['user_id', 'user_name'])
                                        ->toArray(),
            ];

            // Record the event in the event store
            $domainEvent = DomainEvent::create([
                'event_type'   => 'MessageSent',
                'payload'      => json_encode($eventPayload),
                'aggregate_id' => $id, // e.g., linking the event to the chat/event
            ]);

            // Publish the event to RabbitMQ on the 'event_stream' queue
            $this->publishToRabbitMQ('event_stream', json_encode([
                'event_type'   => 'MessageSent',
                'payload'      => $eventPayload,
                'aggregate_id' => $id,
                'event_id'     => $domainEvent->id,
            ]));

            return response()->json(['status' => 'Message sent successfully'], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in sendMessage:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Publish a message to RabbitMQ with fallback to persistent storage if publishing fails.
     */


    private function publishToRabbitMQ($queueName, $messageBody)
    {
        $sslOptions = [
            'cafile'           => '/etc/rabbitmq/certs/ca_certificate.pem',
            'verify_peer'      => true,
            'verify_peer_name' => false
        ];
    
        try {
            $connection = new AMQPSSLConnection(
                env('RABBITMQ_HOST', 'rabbitmq'),
                env('RABBITMQ_PORT', 5671),  // Use SSL port
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                '/',
                $sslOptions
            );
    
            $channel = $connection->channel();
            $channel->queue_declare($queueName, false, true, false, false);
    
            $msg = new AMQPMessage($messageBody);
            $channel->basic_publish($msg, '', $queueName);
    
            $channel->close();
            $connection->close();
    
            Log::info('Message published to RabbitMQ:', ['queue' => $queueName, 'message' => $messageBody]);
        } catch (\Exception $e) {
            Log::error('Error publishing message to RabbitMQ:', [
                'error'   => $e->getMessage(),
                'queue'   => $queueName,
                'message' => $messageBody,
            ]);
    
            $this->storeFailedMessage($queueName, $messageBody);
        }
    }
    

    /**
     * Store the message in the failed_messages table for later retry.
     */
    private function storeFailedMessage($queueName, $messageBody)
    {
        DB::table('failed_messages')->insert([
            'queue_name'    => $queueName,
            'message_body'  => $messageBody,
            'attempt_count' => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        Log::info('Stored failed message for later retry.', ['queue' => $queueName, 'message' => $messageBody]);
    }

    /**
     * Fetch messages for an event.
     */
    public function fetchMessages(Request $request, $id)
    {
        try {
            // Validate the token
            $token = $this->validateToken($request);

            // Parse the token to get user info
            $payload = JWTAuth::setToken($token)->getPayload();
            $userId  = $payload->get('sub');

            // Check if the user is participating in the event
            $isParticipating = EventUser::where('event_id', $id)
                ->where('user_id', $userId)
                ->exists();

            if (!$isParticipating) {
                Log::warning('Unauthorized attempt to fetch messages for an event:', [
                    'user_id'  => $userId,
                    'event_id' => $id,
                ]);
                return response()->json(['error' => 'Unauthorized: User is not participating in this event'], 403);
            }

            // Fetch all messages for the event
            $messages = EventUser::where('event_id', $id)->get();

            return response()->json(['messages' => $messages], 200);
        } catch (\Exception $e) {
            Log::error('Error in fetchMessages:', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Check if a token is blacklisted.
     */
    private function isTokenBlacklisted($token)
    {
        $cacheKey = "blacklisted:{$token}";
        return Cache::has($cacheKey);
    }

    /**
     * Extract the token from the Authorization header.
     */
    private function getTokenFromRequest(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token) {
            throw new \Exception('Token is required.');
        }
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        return $token;
    }

    /**
     * Validate the token and check if it is blacklisted.
     */
    private function validateToken(Request $request)
    {
        $token = $this->getTokenFromRequest($request);
        if ($this->isTokenBlacklisted($token)) {
            throw new \Exception('Unauthorized: Token is blacklisted.');
        }
        return $token;
    }
}
