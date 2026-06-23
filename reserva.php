<?php
/**
 * Seleccion de asientos y creacion de la reserva.
 * No se pide registro, solo nombre y correo (cliente invitado).
 */

require_once __DIR__ . '/config.php';

$resultado_reserva = null;

$funcion_id = filter_input(INPUT_GET, 'funcion', FILTER_VALIDATE_INT)
           ?? filter_input(INPUT_POST, 'funcion_id', FILTER_VALIDATE_INT);

if (!$funcion_id || $funcion_id <= 0) {
    header('Location: cartelera.php');
    exit;
}

// si llega el formulario, intenta crear la reserva
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

$funcion_obj = Funcion::ObtenerPorId($funcion_id);
if (!$funcion_obj) { header('Location: cartelera.php'); exit; }

$mapa_asientos_obj = Asiento::ObtenerMapaPorFuncion($funcion_obj->idSala, $funcion_id);

// la vista esta hecha pensando en arrays, asi que convertimos los objetos
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

// faltan el poster y un par de datos mas que no trae Funcion::ObtenerPorId
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
render_header();
?>


<nav class="bc" style="padding: 10px 20px;">
    <a href="cartelera.php">Cartelera</a> › Selección de asientos
</nav>

<div class="reserva-layout">

    <section>
        <div class="funcion-info">
            <img class="funcion-poster"
                 src="<?= htmlspecialchars($funcion['imagen'] ?? 'assets/img/sin-poster.svg') ?>"
                 alt="Poster"
                 onerror="this.src='assets/img/sin-poster.svg'">
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

<?php render_footer(); ?>
