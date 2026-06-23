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
require_once __DIR__ . '/includes/funciones.php';

$resultado_reserva = null;

$funcion_id = filter_input(INPUT_GET, 'funcion', FILTER_VALIDATE_INT)
           ?? filter_input(INPUT_POST, 'funcion_id', FILTER_VALIDATE_INT);

if (!$funcion_id || $funcion_id <= 0) {
    header('Location: cartelera.php');
    exit;
}

// ── Procesamiento POST usando la clase Reserva ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asientos_raw   = $_POST['asientos'] ?? [];
    $nombre_cliente = trim($_POST['nombre_cliente'] ?? '');
    $email_cliente  = strtolower(trim($_POST['email_cliente'] ?? ''));
    $asientos_ids   = array_filter(array_map('intval', (array)$asientos_raw), fn($id) => $id > 0);

    $reserva           = new Reserva([]);
    $resultado_reserva = $reserva->CrearReserva(
        $funcion_id,
        array_values($asientos_ids),
        $nombre_cliente,
        $email_cliente
    );

    if ($resultado_reserva['exito']) {
        $_SESSION['ultimo_codigo_reserva'] = $resultado_reserva['codigo'];
        header('Location: confirmacion.php?codigo=' . urlencode($resultado_reserva['codigo']));
        exit;
    }
}

// ── Cargar datos de la función usando la clase Funcion ────────
$funcion_obj = Funcion::ObtenerPorId($funcion_id);
if (!$funcion_obj) { header('Location: cartelera.php'); exit; }

// ── Cargar mapa de asientos usando la clase Asiento ──────────
$mapa_asientos_obj = Asiento::ObtenerMapaPorFuncion($funcion_obj->idSala, $funcion_id);

// Convertir objetos Asiento a arrays para compatibilidad con la vista
$mapa_asientos = [];
foreach ($mapa_asientos_obj as $fila => $asientos) {
    foreach ($asientos as $a) {
        $mapa_asientos[$fila][] = [
            'asiento_id'   => $a->id,
            'fila'         => $a->fila,
            'numero'       => $a->numero,
            'tipo_asiento' => $a->tipo,
            'estado'       => $a->estado,
        ];
    }
}

// Mapear objeto Funcion a array para la vista HTML
$funcion = [
    'funcion_id'   => $funcion_obj->id,
    'fecha_hora'   => $funcion_obj->horario,
    'precio'       => $funcion_obj->precio,
    'idioma'       => $funcion_obj->idioma,
    'pelicula'     => $funcion_obj->peliculaTitulo ?? '',
    'sala'         => $funcion_obj->salaNombre     ?? '',
    'tipo_sala'    => '',
    'sala_id'      => $funcion_obj->idSala,
    'imagen'       => '',
];

// Obtener datos adicionales de la función (poster, tipo sala, clasificacion)
$pdo_extra = obtenerConexion();
try {
    $s = $pdo_extra->prepare("
        SELECT p.imagen, p.clasificacion, p.duracion_min, s.tipo AS tipo_sala
        FROM funciones f
        INNER JOIN peliculas p ON p.id = f.pelicula_id
        INNER JOIN salas     s ON s.id = f.sala_id
        WHERE f.id = :id LIMIT 1
    ");
    $s->execute([':id' => $funcion_id]);
    $extra = $s->fetch();
    if ($extra) {
        $funcion['imagen']    = $extra['imagen']    ?? '';
        $funcion['tipo_sala'] = $extra['tipo_sala'] ?? '';
        $funcion['clasificacion'] = $extra['clasificacion'] ?? '';
        $funcion['duracion_min']  = $extra['duracion_min']  ?? 0;
    }
} catch (PDOException $e) {
    registrarError('reserva - datos_extra', $e->getMessage());
}

$precio_fmt  = '$' . number_format($funcion_obj->precio, 0, ',', '.');
$form_nombre = htmlspecialchars($_POST['nombre_cliente'] ?? '', ENT_QUOTES, 'UTF-8');
$form_email  = htmlspecialchars($_POST['email_cliente']  ?? '', ENT_QUOTES, 'UTF-8');

$titulo_pagina = 'Selección de asientos – ' . esc($funcion['pelicula']);
$nav_activo    = 'cartelera';
require_once __DIR__ . '/includes/header.php';
?>

<style>
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

<nav class="bc" style="padding: 10px 20px;">
    <a href="cartelera.php">Cartelera</a> › Selección de asientos
</nav>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
