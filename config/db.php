<?php
/**
 * Conexión centralizada a la base de datos ticketnow.
 * Ajustá host/usuario/clave según tu entorno.
 */

function getPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host = 'localhost';
        $db   = 'ticketnow';
        $user = 'root';
        $pass = '';
        $charset = 'latin1'; // igual al charset de la base ya existente

        $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // usa prepared statements reales
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    return $pdo;
}

/**
 * Headers estándar de JSON + CORS para todos los endpoints de la API.
 */
function apiHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonResponse(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
