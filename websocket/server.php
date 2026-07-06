<?php
/**
 * Servidor WebSocket de TicketNow.
 *
 * Correr como proceso persistente (aparte de Apache/Nginx):
 *   cd websocket && composer install && php server.php
 *
 * Expone DOS puertos:
 *  - 8080 (WebSocket ws://): acá se conectan los navegadores (Asientos.php).
 *    Reciben en vivo los eventos "seat_update" y "stock_update".
 *  - 8081 (TCP interno, solo localhost): acá el backend HTTP (api/*.php)
 *    empuja los eventos cuando alguien reserva/compra/libera un asiento.
 *    NO debe exponerse fuera del servidor (por eso bindea en 127.0.0.1).
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/SeatHub.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as SocketServer;

$loop = LoopFactory::create();
$hub  = new SeatHub();

// --- Canal público para los navegadores ---
$wsApp    = new HttpServer(new WsServer($hub));
$wsSocket = new SocketServer('0.0.0.0:8080', $loop);
new IoServer($wsApp, $wsSocket, $loop);

// --- Canal interno de notificaciones desde el backend PHP ---
$notifySocket = new SocketServer('127.0.0.1:8081', $loop);
$notifySocket->on('connection', function ($conn) use ($hub) {
    $buffer = '';
    $conn->on('data', function ($chunk) use (&$buffer, $conn, $hub) {
        $buffer .= $chunk;
        $data = json_decode($buffer, true);
        if ($data !== null) {
            $hub->broadcast($data);
            $conn->close();
        }
    });
});

echo "TicketNow WebSocket server\n";
echo " - Navegadores conectan a  ws://0.0.0.0:8080\n";
echo " - Backend notifica por    tcp://127.0.0.1:8081\n";

$loop->run();
