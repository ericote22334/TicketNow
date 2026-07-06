<?php
/**
 * Puente entre el backend HTTP (PHP-FPM/Apache, sin estado) y el servidor
 * WebSocket persistente (proceso aparte, corriendo con `php websocket/server.php`).
 *
 * Cómo funciona:
 *  - api/*.php escribe en la base de datos (fuente de verdad)
 *  - si la escritura fue exitosa, abre una conexión TCP corta al puerto interno
 *    del servidor WebSocket (127.0.0.1:8081) y le manda un JSON
 *  - el servidor WebSocket recibe ese JSON y lo reenvía (broadcast) a TODOS
 *    los clientes conectados por WebSocket (los navegadores en Asientos.php)
 *
 * Si el servidor WebSocket estuviera caído, la venta/reserva en la base de
 * datos NO se pierde ni se revierte: solo no habrá actualización instantánea
 * en pantalla (se recupera solo en el próximo refresh/poll de respaldo).
 */

function notificarWebSocket(array $data): bool {
    $host = '127.0.0.1';
    $port = 8081;

    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 1);
    if (!$fp) {
        error_log("[ws_notify] No se pudo conectar al servidor WebSocket: {$errstr} ({$errno})");
        return false;
    }

    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    fclose($fp);
    return true;
}

/**
 * Calcula y devuelve (además de notificar) el stock actual disponible
 * de un evento, para mantener sincronizado el contador global.
 */
function notificarStock(PDO $pdo, int $id_evento): array {
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(a.estado = 'disponible') AS disponibles,
            SUM(a.estado = 'reservado') AS reservados,
            SUM(a.estado = 'vendido') AS vendidos
         FROM asiento a
         INNER JOIN sector s ON s.id_sector = a.id_sector
         WHERE s.id_evento = :id_evento"
    );
    $stmt->execute([':id_evento' => $id_evento]);
    $stock = $stmt->fetch();

    notificarWebSocket([
        'type'       => 'stock_update',
        'id_evento'  => $id_evento,
        'total'      => (int) $stock['total'],
        'disponibles'=> (int) $stock['disponibles'],
        'reservados' => (int) $stock['reservados'],
        'vendidos'   => (int) $stock['vendidos'],
    ]);

    return $stock;
}
