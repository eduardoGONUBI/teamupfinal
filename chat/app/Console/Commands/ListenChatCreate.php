<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ChatOrchestratorController; // Importa o teu controlador

class ListenChatCreate extends Command
{
    // Nome do comando
    protected $signature = 'chat:listen-create';

    // Descrição do comando
    protected $description = 'Listen to chat creation requests via RabbitMQ';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Listening to chat_create_request queue...');
        try {
            $listener = new ChatOrchestratorController();
            $listener->listenChatCreateRequests(); // Chama o método listener
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
