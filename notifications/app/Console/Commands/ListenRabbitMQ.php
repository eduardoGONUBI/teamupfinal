<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\NotificationController;

class ListenRabbitMQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to RabbitMQ queue and process messages';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $notificationController = new NotificationController();
        $notificationController->listenToNotifications();
    }
}
