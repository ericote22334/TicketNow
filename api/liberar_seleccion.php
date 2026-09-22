<?php
/**
 * POST /api/liberar_seleccion.php
 * body JSON: { "id_cliente": 1, "id_evento": 1, "asientos": [123, 124] }
 *
 * Libera en un solo request TODOS los holds ('reservado') de un cliente
 * que todavía no confirmó la compra. Pensado para usarse con
 * navigator.sendBeacon() en los eventos 'pagehide' / 'beforeunload': si el
 * usuario cierra la pestaña, recarga o se va del sitio con asientos
 * seleccionados (o con el carrito armado en la pantalla de "Comprar"),
 * esos asientos no deben quedar "ocupados" para el resto de la gente.
 *
 * A diferencia de cancelar_reserva.php (pensado para un fetch() normal,
 * un asiento a la vez, mientras el usuario sigue en la página), este
 * endpoint acepta varios ids en un solo llamado porque sendBeacon() sólo
 * garantiza UN request en el instante en que la página se está cerrando.
 *
 * Es la misma garantía que ya da cron/liberar_expiradas.php (libera holds
 * de más de 5 minutos), sólo que ésta reacciona al instante en vez de
 * esperar el timeout, cubriendo el caso de "el usuario se fue del sitio".
 */

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';
apiHeaders();

// sendBeacon() en algunos navegadores manda el Blob como POST normal;
// el body siempre es el JSON que armamos en el frontend.
$body = json_decode(file_get_contents('php://input'), true);

$id_cliente = filter_var($body['id_cliente'] ?? null, FILTER_VALIDATE_INT);
$id_evento  = filter_var($body['id_evento'] ?? null, FILTER_VALIDATE_INT);
$asientos   = $body['asientos'] ?? [];

if (!$id_cliente || !is_array($asientos) || count($asientos) === 0) {
    jsonResponse(400, ['error' => 'Faltan datos: id_cliente, asientos[]']);
}
$asientos = array_values(array_unique(array_map('intval', $asientos)));

$pdo = getPDO();
$placeholders = implode(',', array_fill(0, count($asientos), '?'));

// Sólo tocamos asientos que sigan 'reservado' Y sean de ESTE cliente,
// igual que cancelar_reserva.php: nunca liberamos algo que ya se vendió
// o que reservó otra persona.
$stmtLiberar = $pdo->prepare(
    "UPDATE asiento
     SET estado = 'disponible', id_cliente_reserva = NULL, reservado_en = NULL, version = version + 1
     WHERE id_asiento IN ({$placeholders}) AND estado = 'reservado' AND id_cliente_reserva = ?"
);
$stmtLiberar->execute(array_merge($asientos, [$id_cliente]));

$liberados = [];

if ($stmtLiberar->rowCount() > 0) {
    // Volvemos a leer cuáles quedaron efectivamente 'disponible' para
    // avisar por WebSocket sólo esos (no todos los que se pidieron).
    $stmtCheck = $pdo->prepare(
        "SELECT id_asiento FROM asiento WHERE id_asiento IN ({$placeholders}) AND estado = 'disponible'"
    );
    $stmtCheck->execute($asientos);
    $liberados = array_map('intval', array_column($stmtCheck->fetchAll(), 'id_asiento'));

    foreach ($liberados as $id_asiento) {
        notificarWebSocket([
            'type' => 'seat_update',
            'id_asiento' => $id_asiento,
            'estado' => 'disponible',
            'id_evento' => $id_evento,
        ]);
    }
    if ($id_evento) {
        notificarStock($pdo, $id_evento);
    }
}

jsonResponse(200, ['ok' => true, 'liberados' => $liberados]);