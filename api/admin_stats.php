<?php
/**
 * GET /api/admin_stats.php?id_evento=1
 * Agrega todo lo que necesita el panel de Admin en una sola llamada.
 */

require __DIR__ . '/../config/db.php';
apiHeaders();

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT) ?: 1;
$pdo = getPDO();

// Totales generales
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(a.estado='vendido') AS vendidas,
        SUM(a.estado='disponible') AS disponibles,
        SUM(a.estado='reservado') AS reservadas
     FROM asiento a INNER JOIN sector s ON s.id_sector = a.id_sector
     WHERE s.id_evento = :id_evento"
);
$stmt->execute([':id_evento' => $id_evento]);
$totales = $stmt->fetch();
$total = (int) $totales['total'];
$vendidas = (int) $totales['vendidas'];
$ocupacion = $total > 0 ? round(($vendidas / $total) * 100) : 0;

// Recaudación
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS recaudacion FROM venta WHERE id_evento = :id_evento AND estado='confirmada'");
$stmt->execute([':id_evento' => $id_evento]);
$recaudacion = (float) $stmt->fetch()['recaudacion'];

// Ventas por sector (para el gráfico de barras)
$stmt = $pdo->prepare(
    "SELECT s.nombre, SUM(a.estado='vendido') AS vendidas
     FROM sector s LEFT JOIN asiento a ON a.id_sector = s.id_sector
     WHERE s.id_evento = :id_evento GROUP BY s.id_sector ORDER BY s.precio DESC"
);
$stmt->execute([':id_evento' => $id_evento]);
$ventasPorSector = $stmt->fetchAll();

// Ocupación por sector
$stmt = $pdo->prepare(
    "SELECT s.nombre,
            SUM(a.estado IN ('vendido','reservado')) AS ocupados,
            COUNT(a.id_asiento) AS capacidad
     FROM sector s LEFT JOIN asiento a ON a.id_sector = s.id_sector
     WHERE s.id_evento = :id_evento GROUP BY s.id_sector ORDER BY s.precio DESC"
);
$stmt->execute([':id_evento' => $id_evento]);
$ocupacionPorSector = $stmt->fetchAll();

// Sectores disponibles del evento para el panel de administración
$stmt = $pdo->prepare(
    "SELECT id_sector, nombre, capacidad
     FROM sector
     WHERE id_evento = :id_evento
     ORDER BY precio DESC, id_sector ASC"
);
$stmt->execute([':id_evento' => $id_evento]);
$sectores = $stmt->fetchAll();

// Últimas ventas
$stmt = $pdo->prepare(
    "SELECT
        v.id_venta,
        DATE_FORMAT(v.fecha_venta, '%d/%m/%Y %H:%i') AS fecha_venta,
        v.metodo_pago,
        v.total,
        v.estado,
        CONCAT(c.nombre, ' ', c.apellido) AS cliente,
        COUNT(va.id_venta_asiento) AS cantidad_asientos
     FROM venta v
     INNER JOIN cliente c ON c.id_cliente = v.id_cliente
     LEFT JOIN venta_asiento va ON va.id_venta = v.id_venta
     WHERE v.id_evento = :id_evento
     GROUP BY v.id_venta
     ORDER BY v.id_venta DESC
     LIMIT 20"
);
$stmt->execute([':id_evento' => $id_evento]);
$ultimasVentas = $stmt->fetchAll();

jsonResponse(200, [
    'total' => $total,
    'vendidas' => $vendidas,
    'disponibles' => (int) $totales['disponibles'],
    'reservadas' => (int) $totales['reservadas'],
    'ocupacion' => $ocupacion,
    'recaudacion' => $recaudacion,
    'ventas_por_sector' => $ventasPorSector,
    'ocupacion_por_sector' => $ocupacionPorSector,
    'sectores' => $sectores,
    'ultimas_ventas' => $ultimasVentas,
]);
