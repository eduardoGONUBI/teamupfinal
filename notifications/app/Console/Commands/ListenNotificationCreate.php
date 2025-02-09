<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\NotificationOrchestratorController;

class ListenNotificationCreate extends Command
{
    // Nome do comando Artisan
    protected $signature = 'notifications:listen-create';

    // Descrição do comando
    protected $description = 'Listen to notification creation requests via RabbitMQ';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Listening to notification_create_request queue...');
        try {
            $listener = new NotificationOrchestratorController();
            $listener->listenNotificationCreateRequests(); // Chama o método listener
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
