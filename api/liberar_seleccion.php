<?php

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';

apiHeaders();

$body = json_decode(file_get_contents('php://input'), true);

$id_cliente = filter_var($body['id_cliente'] ?? null, FILTER_VALIDATE_INT);
$id_evento  = filter_var($body['id_evento'] ?? null, FILTER_VALIDATE_INT);
$asientos   = $body['asientos'] ?? [];

if (!$id_cliente || !is_array($asientos) || count($asientos) === 0) {
    jsonResponse(400, [
        'error' => 'Faltan datos: id_cliente, asientos[]'
    ]);
}

$asientos = array_values(
    array_unique(
        array_map('intval', $asientos)
    )
);

$pdo = getPDO();

$placeholders = implode(
    ',',
    array_fill(0, count($asientos), '?')
);

$stmtLiberar = $pdo->prepare(
    "UPDATE asiento
     SET estado = 'disponible',
         id_cliente_reserva = NULL,
         reservado_en = NULL,
         version = version + 1
     WHERE id_asiento IN ($placeholders)
       AND estado = 'reservado'
       AND id_cliente_reserva = ?"
);

$stmtLiberar->execute(
    array_merge($asientos, [$id_cliente])
);

$liberados = [];

if ($stmtLiberar->rowCount() > 0) {

    $stmtCheck = $pdo->prepare(
        "SELECT id_asiento
         FROM asiento
         WHERE id_asiento IN ($placeholders)
           AND estado = 'disponible'"
    );

    $stmtCheck->execute($asientos);

    $liberados = array_map(
        'intval',
        array_column(
            $stmtCheck->fetchAll(),
            'id_asiento'
        )
    );

    foreach ($liberados as $id_asiento) {

        notificarWebSocket([
            'type' => 'seat_update',
            'id_asiento' => $id_asiento,
            'estado' => 'disponible',
            'id_evento' => $id_evento
        ]);
    }

    if ($id_evento) {
        notificarStock($pdo, $id_evento);
    }
}

jsonResponse(200, [
    'ok' => true,
    'liberados' => $liberados
]);