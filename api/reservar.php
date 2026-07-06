<?php
/**
 * POST /api/reservar.php
 * body JSON: { "id_asiento": 123, "id_cliente": 1, "id_evento": 1 }
 *
 * =========================================================
 *  BLOQUEO POR CONCURRENCIA (el requisito #3 del enunciado)
 * =========================================================
 * Dos usuarios pueden hacer clic en el mismo asiento en el mismo milisegundo.
 * Para garantizar que sólo uno gane, esto NO se resuelve "a mano" en PHP
 * (leer estado, después decidir, después escribir) porque entre el
 * "leer" y el "escribir" de un usuario se puede colar el otro. Se resuelve
 * en la base de datos con dos capas:
 *
 *  1) SELECT ... FOR UPDATE dentro de una transacción: bloquea la fila
 *     del asiento para cualquier otra transacción hasta que ésta termine
 *     (commit o rollback). El segundo usuario que llega queda esperando
 *     en esta línea hasta que el primero termine.
 *
 *  2) UPDATE ... WHERE estado = 'disponible': aunque el paso 1 ya nos
 *     protege, el UPDATE vuelve a chequear la condición atómicamente.
 *     Si affected_rows = 0, alguien ya se quedó con el asiento -> se
 *     responde 409 "Asiento no disponible" inmediatamente.
 *
 * La reserva es temporal (dura, por ejemplo, 5 minutos - ver
 * cron/liberar_expiradas.php) hasta que se confirma la compra en
 * confirmar_compra.php.
 */

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';
apiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido']);
}

$body = json_decode(file_get_contents('php://input'), true);
$id_asiento = filter_var($body['id_asiento'] ?? null, FILTER_VALIDATE_INT);
$id_cliente = filter_var($body['id_cliente'] ?? null, FILTER_VALIDATE_INT);
$id_evento  = filter_var($body['id_evento'] ?? null, FILTER_VALIDATE_INT);

if (!$id_asiento || !$id_cliente || !$id_evento) {
    jsonResponse(400, ['error' => 'Faltan datos: id_asiento, id_cliente, id_evento']);
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // Paso 1: bloqueamos la fila para que nadie más pueda leerla/tocarla
    // hasta que terminemos esta transacción.
    $stmtLock = $pdo->prepare(
        "SELECT estado FROM asiento WHERE id_asiento = :id_asiento FOR UPDATE"
    );
    $stmtLock->execute([':id_asiento' => $id_asiento]);
    $asiento = $stmtLock->fetch();

    if (!$asiento) {
        $pdo->rollBack();
        jsonResponse(404, ['error' => 'El asiento no existe']);
    }

    if ($asiento['estado'] !== 'disponible') {
        $pdo->rollBack();
        jsonResponse(409, ['error' => 'Asiento no disponible', 'estado_actual' => $asiento['estado']]);
    }

    // Paso 2: escritura atómica con doble chequeo de condición.
    $stmtUpdate = $pdo->prepare(
        "UPDATE asiento
         SET estado = 'reservado', id_cliente_reserva = :id_cliente, reservado_en = NOW(), version = version + 1
         WHERE id_asiento = :id_asiento AND estado = 'disponible'"
    );
    $stmtUpdate->execute([
        ':id_cliente' => $id_cliente,
        ':id_asiento' => $id_asiento,
    ]);

    if ($stmtUpdate->rowCount() === 0) {
        // Alguien ganó la carrera justo entre el SELECT y el UPDATE
        // (defensa extra, en la práctica el FOR UPDATE ya lo evita).
        $pdo->rollBack();
        jsonResponse(409, ['error' => 'Asiento no disponible']);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[reservar.php] ' . $e->getMessage());
    jsonResponse(500, ['error' => 'Error interno al reservar el asiento']);
}

// Avisamos a TODOS los navegadores conectados (excepto que también se lo
// mandamos al que reservó, para que confirme su propia selección) que
// este asiento cambió de color, y actualizamos el contador global.
notificarWebSocket([
    'type'       => 'seat_update',
    'id_asiento' => $id_asiento,
    'estado'     => 'reservado',
    'id_evento'  => $id_evento,
]);
notificarStock($pdo, $id_evento);

jsonResponse(200, [
    'ok' => true,
    'id_asiento' => $id_asiento,
    'estado' => 'reservado',
    'expira_en_segundos' => 300,
]);
