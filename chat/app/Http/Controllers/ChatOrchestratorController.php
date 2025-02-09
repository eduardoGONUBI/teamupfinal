<?php

namespace App\Http\Controllers;

use App\Models\EventUser;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPSSLConnection;

class ChatOrchestratorController extends Controller
{


    public function listenChatCreateRequests()
    {
        $sslOptions = [
            'cafile'           => '/etc/rabbitmq/certs/ca_certificate.pem',
            'verify_peer'      => true,
            'verify_peer_name' => false
        ];
    
        $connection = new AMQPSSLConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5671),  // Use the correct SSL port
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            '/',
            $sslOptions
        );
    
        $channel = $connection->channel();
        $channel->queue_declare('chat_create_request', false, true, false, false);
    
        $callback = function ($msg) use ($channel) {
            $body = json_decode($msg->body, true);
            $corrId = $body['correlation_id'] ?? null;
    
            $response = [
                'status'         => 'ok',
                'message'        => 'Chat created',
                'correlation_id' => $corrId
            ];
    
            try {
                $eventId   = $body['data']['event_id'];
                $eventName = $body['data']['event_name'];
    
            } catch (\Exception $e) {
                $response['status'] = 'error';
                $response['message'] = $e->getMessage();
            }
    
            $channel->queue_declare('chat_create_response', false, true, false, false);
            $channel->basic_publish(
                new AMQPMessage(json_encode($response)),
                '',
                'chat_create_response'
            );
    
            $msg->ack();
        };
    
        $channel->basic_consume('chat_create_request', '', false, false, false, false, $callback);
    
        while ($channel->is_consuming()) {
            $channel->wait();
        }
    
        $channel->close();
        $connection->close();
    }
    
}
