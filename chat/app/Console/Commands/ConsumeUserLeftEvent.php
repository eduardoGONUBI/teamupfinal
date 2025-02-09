<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\DB;

class ConsumeUserLeftEvent extends Command
{
    protected $signature = 'rabbitmq:consume-user-left-event';
    protected $description = 'Consume user_left_event messages from RabbitMQ and anonymize user messages in the event_user table';

    public function handle()
    {
        $this->info('Starting RabbitMQ consumer for user_left_event messages...');

        try {
            // Connect to RabbitMQ
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            // Queue name for user leaving events
            $queueName = 'user_left_event';

            // Declare the queue
            $channel->queue_declare($queueName, false, true, false, false);

            $callback = function (AMQPMessage $msg) {
                try {
                    // Decode the message body
                    $data = json_decode($msg->body, true);

                    if (isset($data['event_id'], $data['user_id'])) {
                        $eventId = $data['event_id'];
                        $userId = $data['user_id'];

                        // Anonymize the user's messages in the event_user table
                        DB::table('event_user')
                            ->where('event_id', $eventId)
                            ->where('user_id', $userId)
                            ->update([
                                'user_name' => 'Anonymous',
                                'user_id' => 999, // Default anonymized user_id
                            ]);

                        $this->info("Anonymized messages for user_id {$userId} in event_id {$eventId}.");
                    } else {
                        $this->error("Invalid message payload: " . $msg->body);
                    }

                    // Acknowledge the message
                    $msg->ack();
                } catch (\Exception $e) {
                    $this->error("Error processing message: " . $e->getMessage());
                }
            };

            // Start consuming messages
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            $this->error('Error consuming RabbitMQ messages: ' . $e->getMessage());
        }
    }
}
