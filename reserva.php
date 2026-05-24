<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : reserva.php
 *  Versión   : 2.0 — Clientes invitados (nombre + email)
 *  Propósito : Selección de asientos y confirmación de reserva.
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';

$resultado_reserva = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado_reserva = procesarReserva();
    if ($resultado_reserva['exito']) {
        $_SESSION['ultimo_codigo_reserva'] = $resultado_reserva['codigo'];
        header('Location: confirmacion.php?codigo=' . urlencode($resultado_reserva['codigo']));
        exit;
    }
}

$funcion_id = filter_input(INPUT_GET, 'funcion', FILTER_VALIDATE_INT)
           ?? filter_input(INPUT_POST, 'funcion_id', FILTER_VALIDATE_INT);

if (!$funcion_id || $funcion_id <= 0) {
    header('Location: cartelera.php');
    exit;
}

function procesarReserva(): array
{
    $funcion_id     = filter_input(INPUT_POST, 'funcion_id', FILTER_VALIDATE_INT);
    $asientos_raw   = $_POST['asientos'] ?? [];
    $nombre_cliente = trim($_POST['nombre_cliente'] ?? '');
    $email_cliente  = strtolower(trim($_POST['email_cliente'] ?? ''));

    if (!$funcion_id || $funcion_id <= 0)
        return ['exito' => false, 'mensaje' => 'Función no válida.', 'codigo' => ''];
    if ($nombre_cliente === '')
        return ['exito' => false, 'mensaje' => 'El nombre es obligatorio.', 'codigo' => ''];
    if (mb_strlen($nombre_cliente) > 150)
        return ['exito' => false, 'mensaje' => 'El nombre no puede superar 150 caracteres.', 'codigo' => ''];
    if (!filter_var($email_cliente, FILTER_VALIDATE_EMAIL))
        return ['exito' => false, 'mensaje' => 'Ingresa un correo electrónico válido.', 'codigo' => ''];
    if (empty($asientos_raw))
        return ['exito' => false, 'mensaje' => 'Debes seleccionar al menos un asiento.', 'codigo' => ''];

    $asientos_ids = array_filter(
        array_map('intval', (array)$asientos_raw),
        fn($id) => $id > 0
    );

    if (count($asientos_ids) > 6)
        return ['exito' => false, 'mensaje' => 'No puedes reservar más de 6 asientos a la vez.', 'codigo' => ''];
    if (empty($asientos_ids))
        return ['exito' => false, 'mensaje' => 'Los asientos seleccionados no son válidos.', 'codigo' => ''];

    $pdo = obtenerConexion();

    try {
        $pdo->beginTransaction();

        $stmtFuncion = $pdo->prepare("
            SELECT id, sala_id, precio
            FROM   funciones
            WHERE  id        = :funcion_id
              AND  activa    = 1
              AND  fecha_hora > NOW()
            FOR UPDATE
        ");
        $stmtFuncion->execute([':funcion_id' => $funcion_id]);
        $funcion = $stmtFuncion->fetch();

        if (!$funcion) {
            $pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'La función no está disponible o ya finalizó.', 'codigo' => ''];
        }

        $placeholders = implode(',', array_map(
            fn($i) => ":asiento_{$i}",
            array_keys($asientos_ids)
        ));

        $sqlBloqueo = "
            SELECT
                a.id    AS asiento_id,
                a.fila,
                a.numero,
                a.sala_id,
                (SELECT COUNT(*) FROM reservas r
                 WHERE r.asiento_id = a.id AND r.funcion_id = :funcion_id
                   AND r.estado IN ('pendiente', 'confirmada')) AS ya_reservado
            FROM asientos a
            WHERE a.id IN ({$placeholders}) AND a.sala_id = :sala_id
            FOR UPDATE
        ";

        $params = [':funcion_id' => $funcion_id, ':sala_id' => $funcion['sala_id']];
        foreach (array_values($asientos_ids) as $i => $id) {
            $params[":asiento_{$i}"] = $id;
        }

        $stmtBloqueo = $pdo->prepare($sqlBloqueo);
        $stmtBloqueo->execute($params);
        $asientos_bloqueados = $stmtBloqueo->fetchAll();

        if (count($asientos_bloqueados) !== count($asientos_ids)) {
            $pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'Uno o más asientos no pertenecen a esta función.', 'codigo' => ''];
        }

        $ocupados = [];
        foreach ($asientos_bloqueados as $a) {
            if ((int)$a['ya_reservado'] > 0) $ocupados[] = $a['fila'] . $a['numero'];
        }

        if (!empty($ocupados)) {
            $pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'Los asientos ' . implode(', ', $ocupados) . ' ya están reservados. Elige otros.', 'codigo' => ''];
        }

        $codigo    = generarCodigoReserva();
        $expiracion = date('Y-m-d H:i:s', strtotime('+' . RESERVA_TIEMPO_LIMITE . ' minutes'));

        $stmtInsert = $pdo->prepare("
            INSERT INTO reservas
                (funcion_id, asiento_id, nombre_cliente, email_cliente,
                 estado, fecha_expiracion, codigo_reserva)
            VALUES
                (:funcion_id, :asiento_id, :nombre_cliente, :email_cliente,
                 'confirmada', :expiracion, :codigo)
        ");

        foreach ($asientos_bloqueados as $asiento) {
            $stmtInsert->execute([
                ':funcion_id'     => $funcion_id,
                ':asiento_id'     => $asiento['asiento_id'],
                ':nombre_cliente' => $nombre_cliente,
                ':email_cliente'  => $email_cliente,
                ':expiracion'     => $expiracion,
                ':codigo'         => $codigo,
            ]);
        }

        $pdo->commit();
        return ['exito' => true, 'mensaje' => '¡Reserva confirmada!', 'codigo' => $codigo];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        registrarError('reserva - procesarReserva', $e->getMessage());
        if ($e->getCode() === '23000')
            return ['exito' => false, 'mensaje' => 'Un asiento fue tomado en el último momento. Por favor elige otros.', 'codigo' => ''];
        return ['exito' => false, 'mensaje' => 'Error interno. Por favor inténtalo de nuevo.', 'codigo' => ''];
    }
}

function generarCodigoReserva(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codigo = 'CF-';
    for ($i = 0; $i < 6; $i++) $codigo .= $chars[random_int(0, strlen($chars) - 1)];
    return $codigo;
}

function obtenerDatosFuncion(int $funcion_id): ?array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("
            SELECT f.id AS funcion_id, f.fecha_hora, f.precio, f.idioma,
                   p.titulo AS pelicula, p.clasificacion, p.duracion_min, p.imagen,
                   s.id AS sala_id, s.nombre AS sala, s.tipo AS tipo_sala
            FROM funciones f
            INNER JOIN peliculas p ON p.id = f.pelicula_id
            INNER JOIN salas     s ON s.id = f.sala_id
            WHERE f.id = :funcion_id AND f.activa = 1 AND f.fecha_hora > NOW()
            LIMIT 1
        ");
        $stmt->execute([':funcion_id' => $funcion_id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        registrarError('reserva - obtenerDatosFuncion', $e->getMessage());
        return null;
    }
}

function obtenerMapaAsientos(int $funcion_id, int $sala_id): array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("
            SELECT a.id AS asiento_id, a.fila, a.numero, a.tipo AS tipo_asiento,
                   COALESCE(
                       (SELECT r.estado FROM reservas r
                        WHERE r.asiento_id = a.id AND r.funcion_id = :funcion_id
                          AND r.estado IN ('pendiente','confirmada') LIMIT 1),
                       'libre') AS estado
            FROM asientos a WHERE a.sala_id = :sala_id
            ORDER BY a.fila ASC, a.numero ASC
        ");
        $stmt->execute([':funcion_id' => $funcion_id, ':sala_id' => $sala_id]);
        $mapa = [];
        foreach ($stmt->fetchAll() as $a) $mapa[$a['fila']][] = $a;
        return $mapa;
    } catch (PDOException $e) {
        registrarError('reserva - obtenerMapaAsientos', $e->getMessage());
        return [];
    }
}

$funcion       = obtenerDatosFuncion($funcion_id);
if (!$funcion) { header('Location: cartelera.php'); exit; }

$mapa_asientos = obtenerMapaAsientos($funcion_id, (int)$funcion['sala_id']);
$precio_fmt    = '$' . number_format($funcion['precio'], 0, ',', '.');
$form_nombre   = htmlspecialchars($_POST['nombre_cliente'] ?? '', ENT_QUOTES, 'UTF-8');
$form_email    = htmlspecialchars($_POST['email_cliente']  ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selección de Asientos – <?= htmlspecialchars($funcion['pelicula']) ?> – CineFlow</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        :root {
            --libre: #2a5c3f; --libre-borde: #2ecc71;
            --ocupado: #3a1a1a; --ocupado-borde: #c0392b;
            --seleccionado: #1a3a5c; --seleccionado-borde: #3498db;
            --preferencial: #3a2e0a; --preferencial-borde: #f39c12;
        }
        .site-header { background: rgba(20,20,20,.95); border-bottom: 2px solid var(--primario); padding: 14px 24px; display: flex; align-items: center; gap: 16px; }
        .site-header .logo { font-size: 1.4rem; font-weight: 700; color: var(--primario); text-decoration: none; }
        .site-header .logo span { color: var(--texto); }
        .bc { color: var(--texto-suave); font-size: .85rem; }
        .bc a { color: var(--primario); text-decoration: none; }
        .reserva-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; max-width: 1100px; margin: 24px auto; padding: 0 20px; }
        @media (max-width: 768px) { .reserva-layout { grid-template-columns: 1fr; } }
        .funcion-info { background: var(--superficie); border: 1px solid var(--borde); border-radius: 12px; padding: 20px; display: flex; gap: 16px; margin-bottom: 20px; }
        .funcion-poster { width: 80px; height: 120px; object-fit: cover; border-radius: 6px; flex-shrink: 0; background: #111; }
        .funcion-datos h1 { font-size: 1.2rem; margin-bottom: 8px; }
        .funcion-meta { font-size: .85rem; color: var(--texto-suave); line-height: 1.8; }
        .pantalla-wrap { text-align: center; margin-bottom: 28px; }
        .pantalla { display: inline-block; background: linear-gradient(to bottom,#fff,#e0e0e0); height: 6px; width: 70%; border-radius: 3px; box-shadow: 0 0 20px rgba(255,255,255,.3); }
        .pantalla-label { font-size: .75rem; color: var(--texto-suave); text-transform: uppercase; letter-spacing: 2px; margin-top: 6px; }
        .mapa-scroll { overflow-x: auto; }
        .mapa-asientos { display: flex; flex-direction: column; gap: 8px; min-width: 320px; }
        .fila-asientos { display: flex; align-items: center; gap: 6px; }
        .fila-label { width: 22px; text-align: center; font-size: .8rem; font-weight: 700; color: var(--texto-suave); flex-shrink: 0; }
        .fila-asientos-grupo { display: flex; gap: 6px; flex-wrap: wrap; }
        .asiento { width: 36px; height: 36px; border-radius: 6px 6px 3px 3px; border: 2px solid; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; cursor: pointer; transition: transform .1s; user-select: none; }
        .asiento:hover:not(.ocupado):not(.confirmado):not(.pendiente) { transform: scale(1.15); }
        .asiento.libre { background: var(--libre); border-color: var(--libre-borde); color: #2ecc71; }
        .asiento.libre.preferencial { background: var(--preferencial); border-color: var(--preferencial-borde); color: #f39c12; }
        .asiento.ocupado, .asiento.confirmado { background: var(--ocupado); border-color: var(--ocupado-borde); color: #c0392b; cursor: not-allowed; opacity: .5; }
        .asiento.pendiente { background: #2e2a0a; border-color: #e67e22; color: #e67e22; cursor: not-allowed; opacity: .7; }
        .asiento.seleccionado { background: var(--seleccionado); border-color: var(--seleccionado-borde); color: #3498db; transform: scale(1.1); box-shadow: 0 0 8px rgba(52,152,219,.4); }
        .leyenda { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 20px; font-size: .8rem; }
        .leyenda-item { display: flex; align-items: center; gap: 6px; }
        .leyenda-box { width: 18px; height: 18px; border-radius: 4px; border: 2px solid; }
        .panel-reserva { position: sticky; top: 20px; align-self: start; }
        .panel-card { background: var(--superficie); border: 1px solid var(--borde); border-radius: 12px; padding: 20px; }
        .panel-card h2 { font-size: 1rem; margin-bottom: 16px; }
        .resumen-vacio { color: var(--texto-suave); font-size: .85rem; text-align: center; padding: 12px 0; }
        #lista-seleccionados { list-style: none; padding: 0; margin: 0 0 12px; }
        #lista-seleccionados li { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--borde); font-size: .88rem; }
        #lista-seleccionados li:last-child { border-bottom: none; }
        .quitar-asiento { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 1rem; }
        .total-precio { display: flex; justify-content: space-between; font-weight: 700; font-size: 1rem; margin: 12px 0; padding-top: 8px; border-top: 1px solid var(--borde); }
        #valor-total { color: var(--primario); }
        .campo-cliente { margin-bottom: 12px; }
        .campo-cliente label { display: block; font-size: .82rem; color: var(--texto-suave); margin-bottom: 5px; }
        .campo-cliente input { width: 100%; padding: 9px 12px; background: #2a2a2a; border: 1px solid var(--borde); border-radius: 6px; color: var(--texto); font-size: .88rem; outline: none; transition: border-color .2s; box-sizing: border-box; }
        .campo-cliente input:focus { border-color: var(--primario); }
        .campo-cliente .hint { font-size: .72rem; color: var(--texto-suave); margin-top: 3px; }
        hr.sep { border: none; border-top: 1px solid var(--borde); margin: 14px 0; }
        .btn-confirmar { width: 100%; padding: 13px; background: var(--primario); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .2s; }
        .btn-confirmar:disabled { background: #555; cursor: not-allowed; }
        .btn-confirmar:hover:not(:disabled) { background: #c0070f; }
        .alerta-error { background: #2e0d0d; border: 1px solid #e74c3c; border-left: 4px solid #e74c3c; color: #e74c3c; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: .9rem; }
    </style>
</head>
<body>

<header class="site-header">
    <a href="index.php" class="logo">Cine<span>Flow</span></a>
    <nav class="bc">
        <a href="cartelera.php">Cartelera</a> › Selección de asientos
    </nav>
</header>

<div class="reserva-layout">

    <section>
        <div class="funcion-info">
            <img class="funcion-poster"
                 src="<?= htmlspecialchars($funcion['imagen'] ?? 'assets/img/sin-poster.jpg') ?>"
                 alt="Poster"
                 onerror="this.src='assets/img/sin-poster.jpg'">
            <div class="funcion-datos">
                <h1><?= htmlspecialchars($funcion['pelicula']) ?></h1>
                <div class="funcion-meta">
                    <div>📅 <?= date('d/m/Y', strtotime($funcion['fecha_hora'])) ?> a las <?= date('H:i', strtotime($funcion['fecha_hora'])) ?> hrs</div>
                    <div>🎭 <?= htmlspecialchars($funcion['sala']) ?> (<?= htmlspecialchars(ucfirst($funcion['tipo_sala'])) ?>)</div>
                    <div>🌐 <?= htmlspecialchars(ucfirst($funcion['idioma'])) ?></div>
                    <div>💵 <?= $precio_fmt ?> por persona</div>
                </div>
            </div>
        </div>

        <?php if ($resultado_reserva !== null): ?>
            <div class="alerta-error" role="alert"><?= htmlspecialchars($resultado_reserva['mensaje']) ?></div>
        <?php endif; ?>

        <div class="pantalla-wrap">
            <div class="pantalla"></div>
            <div class="pantalla-label">Pantalla</div>
        </div>

        <div class="mapa-scroll">
            <div class="mapa-asientos" id="mapa-asientos">
                <?php if (empty($mapa_asientos)): ?>
                    <p style="color:var(--texto-suave);text-align:center;padding:40px 0;">No se pudo cargar el mapa de asientos.</p>
                <?php else: ?>
                    <?php foreach ($mapa_asientos as $fila => $asientos): ?>
                        <div class="fila-asientos">
                            <span class="fila-label"><?= htmlspecialchars($fila) ?></span>
                            <div class="fila-asientos-grupo">
                                <?php foreach ($asientos as $asiento):
                                    $clases = ['asiento', $asiento['estado']];
                                    if ($asiento['tipo_asiento'] === 'preferencial') $clases[] = 'preferencial';
                                    $puede = $asiento['estado'] === 'libre';
                                    $label = "Asiento {$asiento['fila']}{$asiento['numero']}" . ($puede ? '' : ' - ocupado');
                                ?>
                                    <div class="<?= implode(' ', $clases) ?>"
                                         data-id="<?= (int)$asiento['asiento_id'] ?>"
                                         data-fila="<?= htmlspecialchars($asiento['fila']) ?>"
                                         data-numero="<?= (int)$asiento['numero'] ?>"
                                         data-tipo="<?= htmlspecialchars($asiento['tipo_asiento']) ?>"
                                         <?= $puede ? 'onclick="toggleAsiento(this)"' : '' ?>
                                         role="<?= $puede ? 'button' : 'img' ?>"
                                         aria-label="<?= $label ?>" title="<?= $label ?>">
                                        <?= $asiento['numero'] ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="leyenda">
            <div class="leyenda-item"><div class="leyenda-box" style="background:var(--libre);border-color:var(--libre-borde)"></div><span>Libre</span></div>
            <div class="leyenda-item"><div class="leyenda-box" style="background:var(--preferencial);border-color:var(--preferencial-borde)"></div><span>Preferencial</span></div>
            <div class="leyenda-item"><div class="leyenda-box" style="background:var(--ocupado);border-color:var(--ocupado-borde)"></div><span>Ocupado</span></div>
            <div class="leyenda-item"><div class="leyenda-box" style="background:var(--seleccionado);border-color:var(--seleccionado-borde)"></div><span>Tu selección</span></div>
        </div>
    </section>

    <aside class="panel-reserva">
        <div class="panel-card">
            <h2>🎟 Tu selección</h2>

            <div id="resumen-vacio" class="resumen-vacio">Haz clic en un asiento para seleccionarlo.</div>
            <ul id="lista-seleccionados"></ul>

            <div class="total-precio" id="total-wrap" style="display:none;">
                <span>Total</span>
                <span id="valor-total"></span>
            </div>

            <form id="form-reserva" method="POST" action="reserva.php">
                <input type="hidden" name="funcion_id" value="<?= (int)$funcion_id ?>">
                <div id="inputs-asientos"></div>

                <hr class="sep" id="sep-form" style="display:none;">

                <div id="datos-cliente" style="display:none;">
                    <div class="campo-cliente">
                        <label for="nombre-cliente">Nombre completo *</label>
                        <input type="text" name="nombre_cliente" id="nombre-cliente"
                               placeholder="Ej: Juan Pérez" maxlength="150"
                               value="<?= $form_nombre ?>" autocomplete="name" required>
                    </div>
                    <div class="campo-cliente">
                        <label for="email-cliente">Correo electrónico *</label>
                        <input type="email" name="email_cliente" id="email-cliente"
                               placeholder="tu@correo.com" maxlength="255"
                               value="<?= $form_email ?>" autocomplete="email" required>
                        <p class="hint">💡 Úsalo para consultar tu reserva después.</p>
                    </div>
                </div>

                <button type="submit" class="btn-confirmar" id="btn-confirmar" disabled>
                    Confirmar reserva
                </button>
            </form>

            <p style="font-size:.75rem;color:var(--texto-suave);margin-top:12px;text-align:center;">
                🔒 Máximo 6 asientos por transacción.
            </p>
        </div>
    </aside>

</div>

<script>
window.CFReserva = { precio: <?= (float)$funcion['precio'] ?>, maxAsientos: 6 };
</script>
<script src="assets/js/reserva.js"></script>
</body>
</html>
