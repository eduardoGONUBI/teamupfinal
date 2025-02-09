<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;

class NotificationOrchestratorController extends Controller
{
    public function listenNotificationCreateRequests()
    {
        $sslOptions = [
            'cafile'           => '/etc/rabbitmq/certs/ca_certificate.pem', // Path inside container
            'verify_peer'      => true,
            'verify_peer_name' => false
        ];

        $connection = new AMQPSSLConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5671),  // Secure SSL port
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            '/',
            $sslOptions
        );

        $channel = $connection->channel();

        $channel->queue_declare('notification_create_request', false, true, false, false);

        $callback = function ($msg) use ($channel) {
            $body = json_decode($msg->body, true);
            $corrId = $body['correlation_id'] ?? null;

            $response = [
                'status'         => 'ok',
                'message'        => 'Notification created',
                'correlation_id' => $corrId
            ];

            try {
                $eventId = $body['data']['event_id'];
                $eventName = $body['data']['event_name'];

                // Create a notification record in the database
                Notification::create([
                    'event_id'   => $eventId,
                    'event_name' => $eventName,
                    'message'    => 'New notification event processed'
                ]);
            } catch (\Exception $e) {
                $response['status'] = 'error';
                $response['message'] = $e->getMessage();
            }

            $channel->queue_declare('notification_create_response', false, true, false, false);
            $channel->basic_publish(
                new AMQPMessage(json_encode($response)),
                '',
                'notification_create_response'
            );

            $msg->ack();
        };

        $channel->basic_consume('notification_create_request', '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
