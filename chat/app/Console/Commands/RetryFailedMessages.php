<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedMessages extends Command
{
    // Updated signature with new rate limiting options.
    protected $signature = 'rabbitmq:retry-failed 
        {--sleep=60 : Seconds to wait between cycles} 
        {--max-messages=10 : Maximum messages to process per cycle before rate limiting} 
        {--rate-sleep=10 : Seconds to sleep when rate limit is reached}';
    
    protected $description = 'Continuously retry publishing failed messages with a circuit breaker and rate limiting';

    // Circuit Breaker settings
    protected $failureCount = 0;
    protected $failureThreshold = 5; // Number of consecutive failures to trigger the circuit breaker
    protected $circuitOpen = false;
    protected $circuitOpenedAt = null;
    protected $circuitOpenTimeout = 300; // Duration (in seconds) to keep the circuit open (e.g., 5 minutes)

    public function handle()
    {
        $sleepInterval = (int) $this->option('sleep');
        $maxMessages   = (int) $this->option('max-messages');
        $rateSleep     = (int) $this->option('rate-sleep');

        $this->info("Starting continuous retry process with a sleep interval of {$sleepInterval} seconds.");
        $this->info("Rate limiting: max {$maxMessages} messages per cycle, then sleeping for {$rateSleep} seconds.");

        while (true) {
            // Check if the circuit is open
            if ($this->circuitOpen) {
                $elapsed = time() - $this->circuitOpenedAt;
                if ($elapsed >= $this->circuitOpenTimeout) {
                    $this->info("Circuit breaker timeout expired. Closing circuit and resuming processing.");
                    $this->circuitOpen = false;
                    $this->failureCount = 0;
                } else {
                    $this->info("Circuit is open. Skipping processing for " . ($this->circuitOpenTimeout - $elapsed) . " seconds.");
                    sleep($sleepInterval);
                    continue;
                }
            }

            // Retrieve failed messages from the database
            $failedMessages = DB::table('failed_messages')->get();

            if ($failedMessages->isEmpty()) {
                $this->info("No failed messages found. Sleeping for {$sleepInterval} seconds.");
                sleep($sleepInterval);
                continue;
            }

            $messagesProcessed = 0;

            foreach ($failedMessages as $failedMessage) {
                $this->info("Processing failed message ID {$failedMessage->id}...");

                try {
                    // Load RabbitMQ connection settings
                    $host     = env('RABBITMQ_HOST', 'rabbitmq');
                    $port     = env('RABBITMQ_PORT', 5672);
                    $user     = env('RABBITMQ_USER', 'guest');
                    $password = env('RABBITMQ_PASSWORD', 'guest');

                    $this->info("Connecting to RabbitMQ at {$host}:{$port}...");
                    $connection = new AMQPStreamConnection($host, $port, $user, $password);
                    $channel    = $connection->channel();

                    // Ensure the queue exists
                    $channel->queue_declare($failedMessage->queue_name, false, true, false, false);

                    // Create and publish the message
                    $msg = new AMQPMessage($failedMessage->message_body);
                    $channel->basic_publish($msg, '', $failedMessage->queue_name);
                    $this->info("Message ID {$failedMessage->id} published successfully.");

                    // Close the channel and connection
                    $channel->close();
                    $connection->close();

                    // Remove the message from the failed_messages table upon success
                    DB::table('failed_messages')->where('id', $failedMessage->id)->delete();
                    
                    // Reset the failure counter after a successful publish
                    $this->failureCount = 0;
                } catch (\Exception $e) {
                    $this->error("Failed to publish message ID {$failedMessage->id}: " . $e->getMessage());
                    Log::error("Retry failed for message ID {$failedMessage->id}", ['error' => $e->getMessage()]);

                    // Update attempt count in DB
                    DB::table('failed_messages')->where('id', $failedMessage->id)
                        ->update([
                            'attempt_count' => $failedMessage->attempt_count + 1,
                            'updated_at'    => now(),
                        ]);

                    // Increment failure count for the circuit breaker
                    $this->failureCount++;

                    // Check if the failure threshold has been reached
                    if ($this->failureCount >= $this->failureThreshold) {
                        $this->info("Failure threshold reached ({$this->failureCount} consecutive failures). Opening circuit for {$this->circuitOpenTimeout} seconds.");
                        $this->circuitOpen = true;
                        $this->circuitOpenedAt = time();
                        // Break out of the foreach loop to honor the circuit open time
                        break;
                    }
                }

                // Rate limiting: increment messages processed and check if rate limit reached
                $messagesProcessed++;
                if ($messagesProcessed >= $maxMessages) {
                    $this->info("Rate limit reached ({$messagesProcessed} messages processed). Sleeping for {$rateSleep} seconds.");
                    sleep($rateSleep);
                    $messagesProcessed = 0;
                }
            }

            $this->info("Cycle complete. Sleeping for {$sleepInterval} seconds before next check.");
            sleep($sleepInterval);
        }
    }
}
