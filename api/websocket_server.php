<?php
require_once 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class ScannerWebSocket implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $subscribers;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->subscribers = [];
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nieuwe verbinding! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        
        if ($data->type === 'subscribe' && isset($data->scanner_id)) {
            $this->subscribers[$from->resourceId] = $data->scanner_id;
            echo "Client {$from->resourceId} geabonneerd op scanner {$data->scanner_id}\n";
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->subscribers[$conn->resourceId]);
        echo "Verbinding {$conn->resourceId} gesloten\n";
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "Fout: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcastScan($scannerId, $tagId) {
        foreach ($this->clients as $client) {
            if (isset($this->subscribers[$client->resourceId]) && 
                $this->subscribers[$client->resourceId] === $scannerId) {
                $client->send(json_encode([
                    'type' => 'scan',
                    'tag_id' => $tagId
                ]));
            }
        }
    }
}

// Start de WebSocket server
$loop = Factory::create();
$webSocket = new ScannerWebSocket();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $webSocket
        )
    ),
    8080
);

echo "WebSocket server gestart op poort 8080\n";
$server->run(); 