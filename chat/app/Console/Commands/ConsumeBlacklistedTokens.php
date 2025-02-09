<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Cache;

class ConsumeBlacklistedTokens extends Command
{
    protected $signature = 'rabbitmq:consume-blacklisted';
    protected $description = 'Consume blacklisted tokens from RabbitMQ and store them in cache';

    public function handle()
    {
        $this->info('Starting RabbitMQ consumer for blacklisted tokens (Microservice 2)...');

        try {
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            $queueName = 'blacklisted_service2';
            $exchange = 'fanout_exchange';

            $channel->exchange_declare($exchange, 'fanout', false, true, false);
            $channel->queue_declare($queueName, false, true, false, false);
            $channel->queue_bind($queueName, $exchange);

            $callback = function (AMQPMessage $msg) {
                try {
                    $token = $msg->body;

                    // Cache the blacklisted token locally
                    $this->cacheBlacklistedToken($token);

                    $this->info("Blacklisted token processed and cached: {$token}");

                    // Acknowledge the message
                    $msg->ack();
                } catch (\Exception $e) {
                    $this->error("Error processing message: " . $e->getMessage());
                }
            };

            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            $this->error('Error consuming RabbitMQ: ' . $e->getMessage());
        }
    }

    /**
     * Cache the blacklisted token locally.
     *
     * @param string $token
     */
    private function cacheBlacklistedToken($token)
    {
        $ttl = config('jwt.ttl') * 60; // TTL in seconds
        Cache::put("blacklisted:{$token}", true, $ttl);
    }
}
