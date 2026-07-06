<?php
/**
 * Incluir así desde cada página:
 *   $activo = 'inicio' | 'asientos' | 'comprar' | 'admin';
 *   include __DIR__ . '/includes/header.php';
 */
$activo = $activo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TicketNow<?= isset($tituloPagina) ? ' · ' . htmlspecialchars($tituloPagina) : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="tn-navbar navbar navbar-expand-lg py-3">
  <div class="container">
    <a href="index.php" class="tn-logo">
      <span class="tn-logo-icon">🎫</span>
      TICKET<span class="now">NOW</span>
    </a>
    <button class="navbar-toggler border-0 text-light" type="button" data-bs-toggle="collapse" data-bs-target="#tnNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="tnNav">
      <div class="mx-auto d-flex gap-1 mt-3 mt-lg-0">
        <a class="tn-nav-link <?= $activo === 'inicio' ? 'active' : '' ?>" href="index.php">Inicio</a>
        <a class="tn-nav-link <?= $activo === 'asientos' ? 'active' : '' ?>" href="asientos.php">Asientos</a>
        <a class="tn-nav-link <?= $activo === 'comprar' ? 'active' : '' ?>" href="comprar.php">Comprar</a>
        <a class="tn-nav-link <?= $activo === 'admin' ? 'active' : '' ?>" href="admin.php">Admin</a>
      </div>
    </div>
    <div class="d-none d-lg-flex align-items-center gap-3">
      <span class="tn-live-badge"><span class="tn-live-dot"></span> EN VIVO</span>
      <span style="color:var(--text-muted); cursor:pointer;">⚙️</span>
    </div>
  </div>
</nav>
