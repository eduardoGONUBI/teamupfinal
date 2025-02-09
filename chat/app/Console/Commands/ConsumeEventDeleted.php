<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\DB;

class ConsumeEventDeleted extends Command
{
    protected $signature = 'rabbitmq:consume-event-deleted';
    protected $description = 'Consume event_deleted messages from RabbitMQ and delete all records in event_user associated with the event_id';

    public function handle()
    {
        $this->info('Starting RabbitMQ consumer for event_deleted messages...');

        try {
            // Connect to RabbitMQ
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            // Queue name for event deletion messages
            $queueName = 'event_deleted';

            // Declare the queue (should match what is set in the EventController)
            $channel->queue_declare($queueName, false, true, false, false);

            $callback = function (AMQPMessage $msg) {
                try {
                    // Decode the message body
                    $data = json_decode($msg->body, true);

                    if (isset($data['event_id'])) {
                        $eventId = $data['event_id'];

                        // Delete all rows from the event_user table where event_id matches
                        $deletedRecords = DB::table('event_user')->where('event_id', $eventId)->delete();

                        if ($deletedRecords > 0) {
                            $this->info("Successfully deleted {$deletedRecords} records from event_user associated with event_id {$eventId}.");
                        } else {
                            $this->info("No records found in event_user for event_id {$eventId}.");
                        }
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
