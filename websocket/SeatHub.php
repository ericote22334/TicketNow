<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Hub central: mantiene la lista de todos los navegadores conectados
 * por WebSocket y difunde (broadcast) cualquier evento que reciba.
 *
 * No conserva estado del mapa de asientos en memoria: la base de datos
 * MySQL sigue siendo la única fuente de verdad. Este hub solo empuja
 * "avisos" (seat_update / stock_update) para que el navegador actualice
 * su pantalla sin tener que refrescar.
 */
class SeatHub implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
    protected $clientes;

    public function __construct()
    {
        $this->clientes = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clientes->attach($conn);
        echo "[+] Cliente conectado ({$conn->resourceId}). Total: {$this->clientes->count()}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Los navegadores solo escuchan (no envían mensajes de negocio);
        // esto queda disponible por si se quiere agregar un "ping" del cliente.
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clientes->detach($conn);
        echo "[-] Cliente desconectado ({$conn->resourceId}). Total: {$this->clientes->count()}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Envía un mensaje JSON a todos los navegadores conectados.
     */
    public function broadcast(array $data): void
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        foreach ($this->clientes as $cliente) {
            $cliente->send($payload);
        }
        echo "[broadcast] {$payload}\n";
    }
}
