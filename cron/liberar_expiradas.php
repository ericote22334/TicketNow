<?php
/**
 * Libera automáticamente los asientos reservados cuyo tiempo para comprar
 * haya vencido.
 *
 * Programar para correr cada 1-5 segundos, por ejemplo con systemd timer
 * o un loop de shell:
 *   while true; do php cron/liberar_expiradas.php; sleep 1; done
 *
 * El cron tradicional de Linux tiene una frecuencia mínima de 1 minuto,
 * demasiado lenta para una reserva de 15 segundos.
 */

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/reservas.php';
require __DIR__ . '/../helpers/ws_notify.php';

$pdo = getPDO();

// Buscamos asientos reservados vencidos junto con su evento, para poder
// notificar por WebSocket cuáles cambiaron y a qué evento pertenecen.
$stmtBuscar = $pdo->prepare(
    "SELECT a.id_asiento, s.id_evento
     FROM asiento a
     INNER JOIN sector s ON s.id_sector = a.id_sector
     WHERE a.estado = 'reservado'
       AND a.reservado_en < (NOW() - INTERVAL :segundos SECOND)"
);
$stmtBuscar->execute([':segundos' => RESERVA_EXPIRACION_SEGUNDOS]);
$expirados = $stmtBuscar->fetchAll();

if (empty($expirados)) {
    echo "Sin reservas expiradas.\n";
    exit;
}

$stmtLiberar = $pdo->prepare(
    "UPDATE asiento
     SET estado = 'disponible', id_cliente_reserva = NULL, reservado_en = NULL, version = version + 1
     WHERE id_asiento = :id_asiento AND estado = 'reservado'"
);

$eventosAfectados = [];

foreach ($expirados as $row) {
    $stmtLiberar->execute([':id_asiento' => $row['id_asiento']]);
    if ($stmtLiberar->rowCount() > 0) {
        notificarWebSocket([
            'type' => 'seat_update',
            'id_asiento' => $row['id_asiento'],
            'estado' => 'disponible',
            'id_evento' => $row['id_evento'],
        ]);
        $eventosAfectados[$row['id_evento']] = true;
    }
}

foreach (array_keys($eventosAfectados) as $id_evento) {
    notificarStock($pdo, (int) $id_evento);
}

echo count($expirados) . " reserva(s) expirada(s) liberada(s).\n";
