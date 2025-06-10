<?php
/*
 * SmartVisitor WebSocket Server
 * Versie: 1.0
 * Laatste update: 2024-06-08 16:45
 * 
 * Deze server handelt real-time communicatie af tussen verschillende interfaces:
 * - Cloakroom: voor garderobe beheer
 * - Scanner Monitor: voor scanner status
 * - Tag Management: voor tag koppelingen
 * - Admin Dashboard: voor systeem status
 * 
 * Voorbeeld gebruik:
 * 1. Abonneren op een specifieke scanner in de garderobe:
 *    {
 *        "type": "subscribe",
 *        "channel": "cloakroom",
 *        "filter": {
 *            "scanner_id": "123"
 *        }
 *    }
 * 
 * 2. Abonneren op meerdere scanners:
 *    {
 *        "type": "subscribe",
 *        "channel": "cloakroom",
 *        "filter": {
 *            "scanner_id": ["123", "456", "789"]
 *        }
 *    }
 * 
 * 3. Abonneren op alle scanners:
 *    {
 *        "type": "subscribe",
 *        "channel": "cloakroom"
 *    }
 */

require_once __DIR__ . '/../config/config.php';
require_once 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class SmartVisitorWebSocket implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $channels;
    protected $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->channels = [
            'cloakroom' => [],      // Garderobe updates
            'scanner' => [],        // Scanner status updates
            'tag' => [],           // Tag koppeling updates
            'admin' => []          // Systeem status updates
        ];
        
        try {
            $this->pdo = getDbConnection();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logEvent('info', 'websocket', 'WebSocket server gestart');
        } catch (PDOException $e) {
            $this->logEvent('error', 'websocket', 'Database verbinding mislukt: ' . $e->getMessage());
        }
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->logEvent('info', 'websocket', "Nieuwe verbinding! ({$conn->resourceId})");
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg);
            
            if (!isset($data->type)) {
                throw new Exception('Bericht type ontbreekt');
            }

            switch ($data->type) {
                case 'subscribe':
                    if (!isset($data->channel)) {
                        throw new Exception('Kanaal ontbreekt bij subscribe');
                    }
                    $this->handleSubscribe($from, $data);
                    break;

                case 'unsubscribe':
                    if (!isset($data->channel)) {
                        throw new Exception('Kanaal ontbreekt bij unsubscribe');
                    }
                    $this->handleUnsubscribe($from, $data);
                    break;

                case 'broadcast':
                    if (!isset($data->channel) || !isset($data->message)) {
                        throw new Exception('Kanaal of bericht ontbreekt bij broadcast');
                    }
                    $this->handleBroadcast($from, $data);
                    break;

                default:
                    throw new Exception('Onbekend bericht type: ' . $data->type);
            }
        } catch (Exception $e) {
            $this->logEvent('error', 'websocket', 'Fout bij verwerken bericht: ' . $e->getMessage());
            $from->send(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ]));
        }
    }

    protected function handleSubscribe($conn, $data) {
        $channel = $data->channel;
        $filter = $data->filter ?? null;

        if (!isset($this->channels[$channel])) {
            throw new Exception('Onbekend kanaal: ' . $channel);
        }

        $this->channels[$channel][$conn->resourceId] = [
            'filter' => $filter,
            'conn' => $conn
        ];

        $this->logEvent('info', 'websocket', "Client {$conn->resourceId} geabonneerd op kanaal {$channel}" . 
            ($filter ? " met filter: " . json_encode($filter) : ""));
    }

    protected function handleUnsubscribe($conn, $data) {
        $channel = $data->channel;
        
        if (isset($this->channels[$channel][$conn->resourceId])) {
            unset($this->channels[$channel][$conn->resourceId]);
            $this->logEvent('info', 'websocket', "Client {$conn->resourceId} uitgeschreven van kanaal {$channel}");
        }
    }

    protected function handleBroadcast($from, $data) {
        $channel = $data->channel;
        $message = $data->message;
        
        if (!isset($this->channels[$channel])) {
            throw new Exception('Onbekend kanaal: ' . $channel);
        }

        $this->logEvent('info', 'websocket', "Broadcasting naar kanaal {$channel}: " . json_encode($message));

        foreach ($this->channels[$channel] as $subscriber) {
            // Check filter als die is ingesteld
            if ($subscriber['filter'] && !$this->matchesFilter($message, $subscriber['filter'])) {
                continue;
            }

            $subscriber['conn']->send(json_encode([
                'type' => 'message',
                'channel' => $channel,
                'data' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
        }
    }

    protected function matchesFilter($message, $filter) {
        foreach ($filter as $key => $value) {
            if (!isset($message->$key)) {
                return false;
            }

            // Ondersteuning voor arrays in filters (meerdere scanners)
            if (is_array($value)) {
                if (!in_array($message->$key, $value)) {
                    return false;
                }
            } else {
                if ($message->$key !== $value) {
                    return false;
                }
            }
        }
        return true;
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        // Verwijder client uit alle kanalen
        foreach ($this->channels as $channel => $subscribers) {
            if (isset($subscribers[$conn->resourceId])) {
                unset($this->channels[$channel][$conn->resourceId]);
            }
        }
        
        $this->clients->detach($conn);
        $this->logEvent('info', 'websocket', "Verbinding {$conn->resourceId} gesloten");
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        $this->logEvent('error', 'websocket', "Fout: {$e->getMessage()}");
        $conn->close();
    }

    protected function logEvent($level, $context, $message, $extra = null) {
        try {
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO system_logs (log_level, context, message, extra)
                    VALUES (:level, :context, :message, :extra)
                ");
                $stmt->execute([
                    'level' => $level,
                    'context' => $context,
                    'message' => $message,
                    'extra' => $extra ? json_encode($extra) : null
                ]);
            }
        } catch (Exception $e) {
            error_log("WebSocket logging error: " . $e->getMessage());
        }
        
        // Ook naar console loggen
        echo date('Y-m-d H:i:s') . " [{$level}] {$message}\n";
    }
}

// Start de WebSocket server
try {
    $loop = Factory::create();
    $webSocket = new SmartVisitorWebSocket();

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
} catch (Exception $e) {
    echo "Fout bij starten WebSocket server: " . $e->getMessage() . "\n";
    exit(1);
} 