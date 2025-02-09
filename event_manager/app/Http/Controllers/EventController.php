<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Support\Facades\Cache;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\DB;
use App\Services\WeatherService;
use PhpAmqpLib\Connection\AMQPSSLConnection;

class EventController extends Controller
{
    /**
     * Check if a token is blacklisted using the /get-blacklist endpoint.
     */
    private function isTokenBlacklisted($token)
    {
        $cacheKey = "blacklisted:{$token}";

        return Cache::has($cacheKey);
    }

    /**
     * Process token from Authorization header.
     */
    private function getTokenFromRequest(Request $request)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            throw new \Exception('Token is required.');
        }

        // Strip "Bearer " prefix if present
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        return $token;
    }

    /**
     * Validate token and check if blacklisted.
     */
    private function validateToken(Request $request)
    {
        $token = $this->getTokenFromRequest($request);

        if ($this->isTokenBlacklisted($token)) {
            throw new \Exception('Unauthorized: Token is blacklisted.');
        }

        return $token;
    }
    
    /*
    private function publishToRabbitMQ($queueName, $messageBody)
    {
        try {
            // SSL/TLS configuration for RabbitMQ connection
            $ssl_options = [
                'cafile' => base_path('Certificados/ca_certificate.pem'), // Path to the CA certificate
                'verify_peer' => true,                                     // Verify RabbitMQ's certificate
                'verify_peer_name' => true,                                // Verify the peer name in the certificate
            ];
    
            // Create a secure AMQPS connection (with SSL/TLS)
            $connection = new AMQPSSLConnection(
                'rabbitmq',       // RabbitMQ hostname (use the Docker service name if using Docker)
                5671,             // AMQPS port
                'guest',          // RabbitMQ username
                'guest',          // RabbitMQ password
                '/',              // Virtual host (use default '/' or your custom one)
                $ssl_options      // SSL options
            );
    
            // Create a channel for communication
            $channel = $connection->channel();
    
            // Declare the queue (as in the original code)
            $channel->queue_declare($queueName, false, true, false, false);
    
            // Create the message to be sent
            $msg = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT // Make message persistent
            ]);
    
            // Publish the message to the specified queue
            $channel->basic_publish($msg, '', $queueName);
    
            // Close the channel and connection
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            // Optionally, log the error here
            logger()->error("Failed to publish message to RabbitMQ: " . $e->getMessage());
        }
    }
    */

  
    private function publishToRabbitMQ($queueName, $messageBody)
    {
        try {
            $sslOptions = [
                'cafile'      => '/etc/rabbitmq/certs/ca_certificate.pem',
                'verify_peer' => true,
                'verify_peer_name' => false
            ];
            
            // Liga-te à porta 5671 com SSL
            $connection = new AMQPSSLConnection(
                'rabbitmq', 
                5671,        // porta SSL
                'guest', 
                'guest', 
                '/',         // vhost (por omissão "/")
                $sslOptions
            );
    
            $channel = $connection->channel();
    
            // Declarar fila
            $channel->queue_declare($queueName, false, true, false, false);
    
            // Criar a mensagem
            $msg = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);
    
            // Publicar a mensagem
            $channel->basic_publish($msg, '', $queueName);
    
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            // Log ou tratamento de erro
        }
    }
    




    /**
     * Store a new event.
     */
    public function store(Request $request, WeatherService $weatherService)
    {
        try {
            // 1️⃣ Validate inputs
            $validatedData = $request->validate([
                'name' => 'required|string',
                'sport_id' => 'required|exists:sports,id',
                'date' => 'required|date',
                'place' => 'required|string',
                'max_participants' => 'required|integer|min:2',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);
    
            // 2️⃣ Get user data from JWT
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $userId = $payload->get('sub');
            $userName = $payload->get('name');
    
            // 3️⃣ Get weather forecast
            $weatherData = $weatherService->getForecastForDate(
                $validatedData['latitude'],
                $validatedData['longitude'],
                $validatedData['date']
            );
    
            // 4️⃣ Create event in the database
            $event = Event::create([
                'name' => $validatedData['name'],
                'sport_id' => $validatedData['sport_id'],
                'date' => $validatedData['date'],
                'place' => $validatedData['place'],
                'user_id' => $userId,
                'user_name' => $userName,
                'status' => 'in progress',
                'max_participants' => $validatedData['max_participants'],
                'latitude' => $validatedData['latitude'],
                'longitude' => $validatedData['longitude'],
                'weather' => json_encode($weatherData),
            ]);
    
            // 5️⃣ Add creator as a participant
            Participant::create([
                'event_id' => $event->id,
                'user_id' => $userId,
                'user_name' => $userName,
            ]);
    
            // 6️⃣ Publish message to RabbitMQ
            $messageDataForChat = [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'user_id' => $userId,
                'user_name' => $userName,
                'message' => 'User joined the event',
            ];
            $this->publishToRabbitMQ('event_joined', json_encode($messageDataForChat));
    
            // 7️⃣ Orchestrate Chat Creation (Rollback on failure)
            try {
                $this->createChatSagaStep($event->id, $event->name);
            } catch (\Exception $e) {
                $this->rollbackEvent($event->id);
                throw new \Exception("Chat creation failed: " . $e->getMessage());
            }
    
       
    
            // ✅ If everything worked, return success response
            return response()->json([
                'message' => 'Evento criado com sucesso (Chat e Notificações OK)',
                'event' => $event,
                'weather' => $weatherData,
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    
    /*chat saga rollback*/
    private function createChatSagaStep($eventId, $eventName)
    {
        // Generate a unique correlation_id
        $correlationId = uniqid('', true);
    
        // Prepare the request data
        $requestData = [
            'action' => 'create_chat',
            'data' => [
                'event_id'   => $eventId,
                'event_name' => $eventName,
            ],
            'correlation_id' => $correlationId
        ];
    
        // Publish the request to RabbitMQ
        $this->publishToRabbitMQ('chat_create_request', json_encode($requestData));
    
        // Wait for a response
        try {
            $response = $this->waitForRabbitResponse('chat_create_response', $correlationId, 10);
        } catch (\Exception $e) {
            $this->rollbackEvent($eventId);
            throw new \Exception("Chat creation timeout: " . $e->getMessage());
        }
    
        // If response contains an error, rollback
        if ($response['status'] === 'error') {
            $this->rollbackEvent($eventId);
            throw new \Exception("Chat creation failed: " . $response['message']);
        }
    }
    

 /*notification saga rollback*/
 private function createNotificationSagaStep($eventId, $eventName)
 {
     $correlationId = uniqid('', true);
 
     // Get participants of the event
     $participants = Participant::where('event_id', $eventId)
         ->get(['user_id', 'user_name'])
         ->toArray();
 
     // Ensure we have participants before sending notifications
     if (empty($participants)) {
         throw new \Exception("No participants found for event $eventId");
     }
 
     // Construct request payload
     $requestData = [
         'action' => 'create_notification',
         'data' => [
             'event_id'   => $eventId,
             'event_name' => $eventName,
             'participants' => $participants // Include all participants
         ],
         'correlation_id' => $correlationId
     ];
 
     // Publish request to RabbitMQ
     $this->publishToRabbitMQ('notification_create_request', json_encode($requestData));
 
     // Wait for response
     try {
         $response = $this->waitForRabbitResponse('notification_create_response', $correlationId, 10);
     } catch (\Exception $e) {
         $this->rollbackEvent($eventId);
         throw new \Exception("Notification creation failed: " . $e->getMessage());
     }
 
     if ($response['status'] === 'error') {
         $this->rollbackEvent($eventId);
         throw new \Exception("Notification creation failed: " . $response['message']);
     }
 }
 
 
/*rollback saga*/
private function rollbackEvent($eventId)
{
    try {
        // Find the event and delete it
        $event = Event::find($eventId);
        if ($event) {
            $event->delete();
        }

        // Publish "event_deleted" so other services can remove related data
        $this->publishToRabbitMQ('event_deleted', json_encode(['event_id' => $eventId]));

    } catch (\Exception $e) {
        \Log::error("Failed to rollback event $eventId: " . $e->getMessage());
    }
}


private function waitForRabbitResponse($queueName, $correlationId, $timeoutSeconds = 10)
{
    $sslOptions = [
        'cafile' => '/etc/rabbitmq/certs/ca_certificate.pem', // Path to CA certificate
        'verify_peer' => true,
        'verify_peer_name' => false
    ];

    // Create a secure AMQPSSLConnection
    $connection = new \PhpAmqpLib\Connection\AMQPSSLConnection(
        env('RABBITMQ_HOST', 'rabbitmq'),
        5671, // AMQPS port (secure)
        env('RABBITMQ_USER', 'guest'),
        env('RABBITMQ_PASSWORD', 'guest'),
        '/',
        $sslOptions
    );

    $channel = $connection->channel();

    // Ensure the response queue exists
    $channel->queue_declare($queueName, false, true, false, false);

    $responseData = null;
    $start = microtime(true);

    // Callback to process messages
    $callback = function ($msg) use (&$responseData, $correlationId) {
        $data = json_decode($msg->body, true);

        if (isset($data['correlation_id']) && $data['correlation_id'] === $correlationId) {
            $responseData = $data;
            $msg->ack(); // Acknowledge message when found
        } else {
            $msg->nack(false, true); // Requeue if not the expected message
        }
    };

    // Start consuming messages
    $channel->basic_consume($queueName, '', false, false, false, false, $callback);

    // Wait for a response or timeout
    while ($channel->is_consuming() && (microtime(true) - $start < $timeoutSeconds) && !$responseData) {
        $channel->wait(null, false, 1);
    }

    // Close the channel and connection
    $channel->close();
    $connection->close();

    // If no response, throw an exception for timeout
    if (!$responseData) {
        \Log::error("Timeout waiting for response from $queueName (Correlation ID: $correlationId)");
        throw new \Exception("Timeout waiting for response from $queueName");
    }

    return $responseData;
}



    /**
     * Fetch events for a user.
     */
    public function index(Request $request)
    {
        try {
            $token = $this->validateToken($request);

            // Get authenticated user ID from token payload
            $userId = JWTAuth::setToken($token)->getPayload()->get('sub');

            // Fetch only events created by the authenticated user
            $events = Event::with('sport')
                ->where('user_id', $userId)
                ->get();

            // Transform and return events with participant ratings
            $response = $events->map(function ($event) {
                // Fetch participants and their ratings
                $participants = DB::table('event_user')
                    ->where('event_id', $event->id)
                    ->get(['user_id', 'user_name', 'rating']);

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'sport' => $event->sport->name ?? null,
                    'date' => $event->date,
                    'place' => $event->place,
                    'status' => $event->status,
                    'max_participants' => $event->max_participants,
                    'creator' => [
                        'id' => $event->user_id,
                        'name' => $event->user_name,
                    ],
                    'participants' => $participants->map(function ($participant) {
                        return [
                            'id' => $participant->user_id,
                            'name' => $participant->user_name,
                            'rating' => $participant->rating,
                        ];
                    }),
                ];
            });

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }


    /**
     * Delete an event.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $token = $this->validateToken($request);

            // Get authenticated user ID from token payload
            $userId = JWTAuth::setToken($token)->getPayload()->get('sub');

            // Find the event
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Check if the user is the creator of the event
            if ((int) $event->user_id !== (int) $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Store event ID for publishing to RabbitMQ
            $eventDataForChatDeletion = ['event_id' => $event->id];

            // Delete the event
            $event->delete();

            // Publish a message to RabbitMQ with the event ID
            $this->publishToRabbitMQ('event_deleted', json_encode($eventDataForChatDeletion));

            return response()->json(['message' => 'Event deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }


    /**
     * Update an event.
     */
    public function update(Request $request, $id)
    {
        try {
            $token = $this->validateToken($request);
            $userId = JWTAuth::setToken($token)->getPayload()->get('sub');

            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            if ((int) $event->user_id !== (int) $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validatedData = $request->validate([
                'name' => 'string|nullable',
                'sport_id' => 'exists:sports,id|nullable',
                'date' => 'date|nullable',
                'place' => 'string|nullable',
                'max_participants' => 'integer|min:2|nullable',
            ]);

            $event->update($validatedData);

            // Fetch all participants of the updated event
            $participants = Participant::where('event_id', $id)
                ->distinct()
                ->get(['user_id', 'user_name'])
                ->toArray();

            // Prepare the notification message
            $notificationMessage = [
                'type'         => 'new_message',
                'event_id'     => $id,
                'event_name'   => $event->name ?? "Evento $id",
                'user_id'      => $userId,
                'user_name'    => $this->getUserNameFromToken($token), // Implement this method if needed
                'message'      => 'Event was Updated',
                'timestamp'    => now()->toISOString(),
                'participants' => $participants,
            ];

            // Publish the message to the RabbitMQ queue named 'notification'
            $this->publishToRabbitMQ('notification', json_encode($notificationMessage));

            return response()->json(['message' => 'Event updated successfully', 'event' => $event], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Optionally, define this helper method if you need the user_name from the token.
     * If the token payload contains 'name', you can retrieve it similarly to user_id.
     */
    private function getUserNameFromToken($token)
    {
        $payload = JWTAuth::setToken($token)->getPayload();
        return $payload->get('name') ?? null;
    }


    /**
     * Join an event.
     */
    public function join(Request $request, $id)
    {
        try {
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $userId = $payload->get('sub');
            $userName = $payload->get('name');

            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Prevent joining a concluded event
            if ($event->status === 'concluded') {
                return response()->json(['error' => 'This event has concluded. You can no longer join.'], 400);
            }

            if ($event->user_id === $userId) {
                return response()->json(['error' => 'You cannot join your own event'], 400);
            }

            $currentParticipantCount = Participant::where('event_id', $id)->count();

            if ($currentParticipantCount >= $event->max_participants) {
                return response()->json(['error' => 'Event is full'], 400);
            }

            if (Participant::where('event_id', $id)->where('user_id', $userId)->exists()) {
                return response()->json(['error' => 'You have already joined this event'], 400);
            }

            // Add the user as a participant
            Participant::create([
                'event_id' => $id,
                'user_id' => $userId,
                'user_name' => $userName,
            ]);


            // Fetch all participants to notify
            $participants = Participant::where('event_id', $id)->get(['user_id', 'user_name'])->toArray();

            // Prepare the notification message without calling ->toArray() again on $participants
            $notificationMessage = [
                'type' => 'new_message',
                'event_id' => $id,
                'event_name' => "Evento $id",
                'user_id' => $userId,
                'user_name' => $userName,
                'message' => 'A User has joined the event',
                'timestamp' => now()->toISOString(),
                'participants' => $participants, // Already an array
            ];

            // Publish the message to the RabbitMQ queue named 'notification'
            $this->publishToRabbitMQ('notification', json_encode($notificationMessage));


            // Publish a message specifically for the chat service queue after a user joins the event.
            $messageDataForChat = [
                'event_id' => $id,
                'event_name' => $event->name,
                'user_id' => $userId,
                'user_name' => $userName,
                'message' => 'User joined the event'
            ];

            // Publish this to the 'event_joined' queue (or whichever queue the chat microservice listens to)
            $this->publishToRabbitMQ('event_joined', json_encode($messageDataForChat));


            return response()->json(['message' => 'You have successfully joined the event'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    public function participants(Request $request, $id)
    {
        try {
            $token = $this->validateToken($request);

            $payload = JWTAuth::setToken($token)->getPayload();
            $userId = $payload->get('sub');

            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Check if the user is authorized
            $isAuthorized = ($event->user_id === $userId) || Participant::where('event_id', $id)->where('user_id', $userId)->exists();

            if (!$isAuthorized) {
                return response()->json(['error' => 'Unauthorized: You do not have access to view this event'], 403);
            }

            // Retrieve participants along with their ratings
            $participants = DB::table('event_user')
                ->where('event_id', $id)
                ->get(['user_id', 'rating']);

            $participantDetails = $participants->map(function ($participant) {
                return [
                    'id' => $participant->user_id,
                    'rating' => $participant->rating,
                ];
            });

            // Include creator details
            $creatorDetails = [
                'id' => $event->user_id,
                'name' => $event->user_name,
            ];

            return response()->json([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'creator' => $creatorDetails,
                'participants' => $participantDetails,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Fetch user events.
     */
    public function userEvents(Request $request)
    {
        try {
            $token = $this->validateToken($request);

            // Get authenticated user ID from token payload
            $userId = JWTAuth::setToken($token)->getPayload()->get('sub');

            // Fetch events where the user is either the creator or a participant
            $events = Event::with('sport')
                ->where('user_id', $userId)
                ->orWhereHas('participants', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->get();

            // Transform and return events with participant ratings
            $response = $events->map(function ($event) {
                // Fetch participants and their ratings
                $participants = DB::table('event_user')
                    ->where('event_id', $event->id)
                    ->get(['user_id', 'user_name', 'rating']);


                      // Decode the weather data and extract only the temperature
                      $weatherData = json_decode($event->weather, true);

                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                        'sport' => $event->sport->name ?? null,
                        'date' => $event->date,
                        'place' => $event->place,
                        'status' => $event->status,
                        'max_participants' => $event->max_participants,
                        'creator' => [
                            'id' => $event->user_id,
                            'name' => $event->user_name,
                        ],
                          'weather' => [
                    'app_max_temp' => $weatherData['app_max_temp'] ?? 'N/A',
                    'app_min_temp' => $weatherData['app_min_temp'] ?? 'N/A',
                    'temp' => $weatherData['temp'] ?? 'N/A',
                    'high_temp' => $weatherData['high_temp'] ?? 'N/A',
                    'low_temp' => $weatherData['low_temp'] ?? 'N/A',
                    'description' => $weatherData['weather']['description'] ?? 'N/A',
                ],
                        'participants' => $participants->map(function ($participant) {
                            return [
                                'id' => $participant->user_id,
                                'name' => $participant->user_name,
                                'rating' => $participant->rating,
                        ];
                    }),
                ];
            });

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Search for events.
     */
    public function search(Request $request)
    {
        try {
            $token = $this->validateToken($request);

            $query = Event::query();

            if ($request->has('id')) {
                $query->where('id', $request->input('id'));
            }

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }

            if ($request->has('date')) {
                $query->where('date', $request->input('date'));
            }

            if ($request->has('place')) {
                $query->where('place', 'like', '%' . $request->input('place') . '%');
            }

            $events = $query->get();

            if ($events->isEmpty()) {
                return response()->json(['message' => 'No events found'], 200);
            }

            return response()->json($events, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Admin List all events
     */
    public function listAllEvents(Request $request)
    {
        try {
            // Validate token and check admin privilege
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $isAdmin = $payload->get('is_admin'); // Assuming the payload includes an 'is_admin' field

            if (!$isAdmin) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Fetch all events with participants and their ratings
            $events = Event::with(['sport'])->get();

            // Transform and return response
            $response = $events->map(function ($event) {
                $participants = DB::table('event_user')
                    ->where('event_id', $event->id)
                    ->get(['user_id', 'rating']);

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'sport' => $event->sport->name ?? null,
                    'date' => $event->date,
                    'place' => $event->place,
                    'status' => $event->status,
                    'max_participants' => $event->max_participants,
                    'creator' => [
                        'id' => $event->user_id,
                        'name' => $event->user_name,
                    ],
                    'participants' => $participants->map(function ($participant) {
                        return [
                            'id' => $participant->user_id,
                            'rating' => $participant->rating,
                        ];
                    }),
                ];
            });

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Admin delete all events
     */
    public function deleteEventAsAdmin(Request $request, $id)
    {
        try {
            // Validate token and check admin privilege
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $isAdmin = $payload->get('is_admin'); // Assuming the payload includes an 'is_admin' field

            if (!$isAdmin) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Find the event
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Store event ID for publishing to RabbitMQ
            $eventDataForChatDeletion = ['event_id' => $event->id];

            // Delete the event
            $event->delete();

            // Publish a message to RabbitMQ with the event ID (same as in destroy method)
            $this->publishToRabbitMQ('event_deleted', json_encode($eventDataForChatDeletion));

            return response()->json(['message' => 'Event deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Admin concludes event
     */
    public function concludeEvent(Request $request, $id)
    {
        try {
            // Validate token and check admin privileges
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $isAdmin = $payload->get('is_admin');

            if (!$isAdmin) {
                return response()->json(['error' => 'Unauthorized: Admins only'], 403);
            }

            // Find the event
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Update the status to 'concluded'
            $event->status = 'concluded';
            $event->save();

            return response()->json(['message' => 'Event status updated to concluded', 'event' => $event], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to conclude event: ' . $e->getMessage()], 500);
        }
    }


    /**
     * User Leaves event
     */
    public function leave(Request $request, $id)
    {
        try {
            // Validate the token and retrieve the user info
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $userId = $payload->get('sub');
            $userName = $payload->get('name'); // Add userName for the notification message

            // Find the event by ID
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Check if the user is a participant of the event
            $participant = Participant::where('event_id', $id)->where('user_id', $userId)->first();

            if (!$participant) {
                return response()->json(['error' => 'You are not a participant of this event'], 400);
            }

            // Delete the participant record to leave the event
            $participant->delete();

            // Publish a message to RabbitMQ to inform the chat microservice
            $messageDataForChat = [
                'event_id' => $id,
                'user_id' => $userId
            ];

            $this->publishToRabbitMQ('user_left_event', json_encode($messageDataForChat));

            // Fetch all remaining participants to notify
            $remainingParticipants = Participant::where('event_id', $id)->get(['user_id', 'user_name'])->toArray();


            // Prepare the notification message without calling ->toArray() again on $participants
            $notificationMessage = [
                'type' => 'new_message',
                'event_id' => $id,
                'event_name' => "Evento $id",
                'user_id' => $userId,
                'user_name' => $userName,
                'message' => 'A user has left the event',
                'timestamp' => now()->toISOString(),
                'participants' => $remainingParticipants, // Already an array
            ];

            // Publish the message to the RabbitMQ queue named 'notification'
            $this->publishToRabbitMQ('notification', json_encode($notificationMessage));

            return response()->json(['message' => 'You have successfully left the event'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }


    /**
     * Creator kicks user from event
     */
    public function kickParticipant(Request $request, $event_id, $user_id)
    {
        try {
            // Validate the token and get the authenticated user's info
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $currentUserId = $payload->get('sub');

            // Find the event
            $event = Event::find($event_id);

            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            // Check if the current user is the creator of the event
            if ((int) $event->user_id !== (int) $currentUserId) {
                return response()->json(['error' => 'You are not the creator of this event'], 403);
            }

            // Check if the user to be kicked is a participant
            $participant = Participant::where('event_id', $event_id)
                ->where('user_id', $user_id)
                ->first();

            if (!$participant) {
                return response()->json(['error' => 'User is not a participant of this event'], 404);
            }

            // Store the participant's name before deleting
            $kickedUserName = $participant->user_name;

            // Kick the participant out (delete the participant record)
            $participant->delete();

            // Publish a message to RabbitMQ to inform the chat microservice that the user left (or was kicked)
            $messageDataForChat = [
                'event_id' => $event_id,
                'user_id'  => $user_id,
            ];

            // Using the same queue as the "leave" method
            $this->publishToRabbitMQ('user_left_event', json_encode($messageDataForChat));

            // Fetch all remaining participants to notify
            $remainingParticipants = Participant::where('event_id', $event_id)->get(['user_id', 'user_name'])->toArray();

            // Prepare the notification message
            $notificationMessage = [
                'type'         => 'new_message',
                'event_id'      => $event_id,
                'event_name'    => $event->name ?? "Evento $event_id",
                'user_id'       => $user_id,
                'user_name'     => $kickedUserName,
                'message'       => 'A user was kicked from the event',
                'timestamp'     => now()->toISOString(),
                'participants'  => $remainingParticipants,
            ];

            // Publish the notification
            $this->publishToRabbitMQ('notification', json_encode($notificationMessage));

            return response()->json(['message' => 'Participant kicked out successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }


    public function rateUser(Request $request, $event_id)
    {
        try {
            // Validate token and get authenticated user's ID
            $token = $this->validateToken($request);
            $payload = JWTAuth::setToken($token)->getPayload();
            $authUserId = $payload->get('sub'); // Get the authenticated user's ID

            // Validate incoming data
            $validated = $request->validate([
                'user_id' => 'required|exists:event_user,user_id', // Ensure the user being rated exists in event_user
                'rating' => 'required|integer|min:1|max:5',        // Ensure rating is an integer between 1 and 5
            ]);

            // Find the event
            $event = Event::findOrFail($event_id);

            // Check if the event is concluded
            if ($event->status !== 'concluded') {
                return response()->json(['error' => 'Event must be concluded to rate participants'], 400);
            }

            // Check if the authenticated user participated in the event
            if (!Participant::where('event_id', $event_id)->where('user_id', $authUserId)->exists()) {
                return response()->json(['error' => 'You did not participate in this event'], 403);
            }

            // Check if the user being rated participated in the event
            if (!Participant::where('event_id', $event_id)->where('user_id', $validated['user_id'])->exists()) {
                return response()->json(['error' => 'The user being rated did not participate in this event'], 403);
            }

            // Update the rating in the event_user table
            DB::table('event_user')
                ->where('event_id', $event_id)
                ->where('user_id', $validated['user_id'])
                ->update(['rating' => $validated['rating']]);

            return response()->json(['message' => 'Rating updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
