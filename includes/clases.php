<?php
/**
 * Las 5 clases del diagrama UML, todas en este archivo:
 * Pelicula, Funcion, Sala, Asiento y Reserva.
 *
 * Solo dejamos los metodos que de verdad usan las vistas, para
 * no tener codigo de mas que despues hay que explicar.
 */

if (defined('CINEFLOW_CLASES')) return;
define('CINEFLOW_CLASES', true);


/**
 * Clase Pelicula
 * Representa una película y las consultas de catálogo/cartelera.
 */
class Pelicula
{
    public int    $id;
    public string $titulo;
    public int    $duracion;   // minutos
    public string $poster;     // ruta de la imagen

    public string $genero        = '';
    public string $clasificacion = '';
    public string $sinopsis      = '';
    public ?float $precioDesde   = null;

    public function __construct(array $fila)
    {
        $this->id            = (int)$fila['id'];
        $this->titulo        = $fila['titulo'] ?? '';
        $this->duracion      = (int)($fila['duracion_min'] ?? 0);
        $this->poster        = $fila['imagen'] ?? '';
        $this->genero        = $fila['genero'] ?? '';
        $this->clasificacion = $fila['clasificacion'] ?? '';
        $this->sinopsis      = $fila['sinopsis'] ?? '';
        $this->precioDesde   = isset($fila['precio_desde']) ? (float)$fila['precio_desde'] : null;
    }

    /** Lista películas activas con funciones próximas (usada en el Inicio). */
    public static function ListarPeliculas(int $limite = 0): array
    {
        $pdo = obtenerConexion();
        try {
            $sql = "
                SELECT DISTINCT p.id, p.titulo, p.genero, p.clasificacion,
                    p.duracion_min, p.imagen, p.sinopsis,
                    (SELECT MIN(f2.precio) FROM funciones f2
                     WHERE f2.pelicula_id = p.id AND f2.activa = 1 AND f2.fecha_hora >= NOW()) AS precio_desde
                FROM peliculas p
                INNER JOIN funciones f ON f.pelicula_id = p.id
                WHERE p.activa = 1 AND f.activa = 1 AND f.fecha_hora >= NOW()
                ORDER BY p.fecha_estreno DESC
            ";
            if ($limite > 0) $sql .= " LIMIT :limite";
            $stmt = $pdo->prepare($sql);
            if ($limite > 0) $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(fn($fila) => new self($fila), $stmt->fetchAll());
        } catch (PDOException $e) {
            registrarError('Pelicula::ListarPeliculas', $e->getMessage());
            return [];
        }
    }

    /** Cartelera completa: películas activas con sus funciones agrupadas por fecha. */
    public static function ObtenerCartelera(): array
    {
        $pdo = obtenerConexion();
        try {
            $stmtPelis = $pdo->query("
                SELECT DISTINCT p.id, p.titulo, p.genero, p.clasificacion,
                    p.duracion_min, p.imagen, p.sinopsis,
                    (SELECT MIN(f2.precio) FROM funciones f2
                     WHERE f2.pelicula_id = p.id AND f2.activa = 1 AND f2.fecha_hora >= NOW()) AS precio_desde
                FROM peliculas p
                INNER JOIN funciones f ON f.pelicula_id = p.id
                WHERE p.activa = 1 AND f.activa = 1 AND f.fecha_hora >= NOW()
                ORDER BY p.fecha_estreno DESC
            ");

            // Funciones de los próximos 7 días, para agrupar por película/fecha
            $todasFunciones = $pdo->query("
                SELECT f.id, f.pelicula_id, f.fecha_hora, f.precio, f.idioma,
                    s.nombre AS sala_nombre, s.tipo AS tipo_sala,
                    (SELECT COUNT(*) FROM asientos a WHERE a.sala_id = f.sala_id) -
                    (SELECT COUNT(*) FROM reservas r WHERE r.funcion_id = f.id AND r.estado IN ('pendiente','confirmada'))
                    AS asientos_libres
                FROM funciones f
                INNER JOIN salas s ON s.id = f.sala_id
                WHERE f.activa = 1 AND f.fecha_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY f.fecha_hora ASC
            ")->fetchAll();

            $resultado = [];
            foreach ($stmtPelis->fetchAll() as $filaPeli) {
                $pelicula = new self($filaPeli);

                $porFecha = [];
                foreach ($todasFunciones as $f) {
                    if ((int)$f['pelicula_id'] !== $pelicula->id) continue;
                    $fecha = substr($f['fecha_hora'], 0, 10); // YYYY-MM-DD
                    $porFecha[$fecha][] = $f;
                }

                $resultado[] = ['pelicula' => $pelicula, 'funciones_por_fecha' => $porFecha];
            }

            return $resultado;
        } catch (PDOException $e) {
            registrarError('Pelicula::ObtenerCartelera', $e->getMessage());
            return [];
        }
    }
}


/**
 * Clase Funcion
 * Una proyección de una Pelicula en una Sala, a una fecha y hora.
 * Relaciones UML: Agrega Pelicula, Agrega Sala, Compone Asiento[].
 */
class Funcion
{
    public int    $id;
    public int    $idPelicula;
    public int    $idSala;
    public string $horario;   // fecha_hora ISO
    public float  $precio;

    public string  $idioma         = 'castellano';
    public bool    $activa         = true;
    public ?string $peliculaTitulo = null;
    public ?string $salaNombre     = null;
    public int     $asientosLibres = 0;

    public function __construct(array $fila)
    {
        $this->id             = (int)$fila['id'];
        $this->idPelicula     = (int)($fila['pelicula_id'] ?? 0);
        $this->idSala         = (int)($fila['sala_id']     ?? 0);
        $this->horario        = $fila['fecha_hora']        ?? '';
        $this->precio         = (float)($fila['precio']    ?? 0);
        $this->idioma         = $fila['idioma']             ?? 'castellano';
        $this->activa         = (bool)($fila['activa']     ?? true);
        $this->peliculaTitulo = $fila['pelicula_titulo']   ?? null;
        $this->salaNombre     = $fila['sala_nombre']        ?? null;
        $this->asientosLibres = (int)($fila['asientos_libres'] ?? 0);
    }

    /** Obtiene una función por su ID con los datos de película y sala. */
    public static function ObtenerPorId(int $id): ?self
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT f.id, f.pelicula_id, f.sala_id, f.fecha_hora, f.precio, f.idioma, f.activa,
                    p.titulo AS pelicula_titulo, p.clasificacion, p.duracion_min, p.imagen,
                    s.nombre AS sala_nombre, s.tipo AS sala_tipo,
                    (SELECT COUNT(*) FROM asientos a WHERE a.sala_id = f.sala_id) -
                    (SELECT COUNT(*) FROM reservas r WHERE r.funcion_id = f.id AND r.estado IN ('pendiente','confirmada'))
                    AS asientos_libres
                FROM funciones f
                INNER JOIN peliculas p ON p.id = f.pelicula_id
                INNER JOIN salas     s ON s.id = f.sala_id
                WHERE f.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch();
            return $fila ? new self($fila) : null;
        } catch (PDOException $e) {
            registrarError('Funcion::ObtenerPorId', $e->getMessage());
            return null;
        }
    }
}


/**
 * Clase Sala
 * Representa una sala de cine (relación Agrega con Funcion).
 */
class Sala
{
    public int    $id;
    public int    $numAsientos;

    public string $nombre = '';
    public string $tipo   = '';  // estandar, vip, 4dx, imax

    public function __construct(array $fila)
    {
        $this->id          = (int)$fila['id'];
        $this->numAsientos = (int)($fila['num_asientos'] ?? 0);
        $this->nombre      = $fila['nombre'] ?? '';
        $this->tipo        = $fila['tipo']   ?? '';
    }
}


/**
 * Clase Asiento
 * Un asiento físico de una sala y su estado para una función dada.
 */
class Asiento
{
    public int    $id;
    public string $fila;
    public int    $numero;
    public string $estado;   // libre | pendiente | confirmada | utilizada

    public int    $salaId = 0;
    public string $tipo   = 'estandar';

    public function __construct(array $fila)
    {
        $this->id     = (int)$fila['id'];
        $this->fila   = $fila['fila']   ?? '';
        $this->numero = (int)($fila['numero'] ?? 0);
        $this->estado = $fila['estado'] ?? 'libre';
        $this->salaId = (int)($fila['sala_id'] ?? 0);
        $this->tipo   = $fila['tipo']   ?? 'estandar';
    }

    /** Mapa de asientos de una sala con su estado actual para una función. */
    public static function ObtenerMapaPorFuncion(int $salaId, int $funcionId): array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT a.id, a.fila, a.numero, a.sala_id, a.tipo,
                    COALESCE((
                        SELECT r.estado FROM reservas r
                        WHERE r.asiento_id = a.id AND r.funcion_id = :funcion_id
                          AND r.estado IN ('pendiente','confirmada')
                        LIMIT 1
                    ), 'libre') AS estado
                FROM asientos a
                WHERE a.sala_id = :sala_id
                ORDER BY a.fila ASC, a.numero ASC
            ");
            $stmt->execute([':funcion_id' => $funcionId, ':sala_id' => $salaId]);

            $mapa = [];
            foreach ($stmt->fetchAll() as $fila) {
                $asiento = new self($fila);
                $mapa[$asiento->fila][] = $asiento;
            }
            return $mapa;
        } catch (PDOException $e) {
            registrarError('Asiento::ObtenerMapaPorFuncion', $e->getMessage());
            return [];
        }
    }
}


/**
 * Clase Reserva
 * Lógica de negocio para crear y consultar reservas.
 * Una reserva puede tener varios asientos (mismo código de reserva).
 */
class Reserva
{
    public int    $id;
    public string $nombreCliente;
    public string $email;
    public int    $idFuncion;

    public string $codigoReserva   = '';
    public string $estado          = 'confirmada';
    public string $fechaReserva    = '';
    public string $fechaExpiracion = '';

    public function __construct(array $fila)
    {
        $this->id              = (int)($fila['id']            ?? 0);
        $this->nombreCliente   = $fila['nombre_cliente']       ?? '';
        $this->email           = $fila['email_cliente']        ?? '';
        $this->idFuncion       = (int)($fila['funcion_id']    ?? 0);
        $this->codigoReserva   = $fila['codigo_reserva']       ?? '';
        $this->estado          = $fila['estado']               ?? 'confirmada';
        $this->fechaReserva    = $fila['fecha_reserva']        ?? '';
        $this->fechaExpiracion = $fila['fecha_expiracion']     ?? '';
    }

    /**
     * Crea la reserva (uno o varios asientos) dentro de una transacción.
     * El SELECT ... FOR UPDATE bloquea la función y los asientos pedidos
     * para que no se pueda vender el mismo asiento dos veces (RN-01).
     */
    public function CrearReserva(int $funcionId, array $asientoIds, string $nombreCliente, string $email): array
    {
        if ($funcionId <= 0)
            return ['exito' => false, 'mensaje' => 'Función no válida.', 'codigo' => ''];
        if (trim($nombreCliente) === '')
            return ['exito' => false, 'mensaje' => 'El nombre es obligatorio.', 'codigo' => ''];
        if (mb_strlen($nombreCliente) > 150)
            return ['exito' => false, 'mensaje' => 'El nombre no puede superar 150 caracteres.', 'codigo' => ''];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            return ['exito' => false, 'mensaje' => 'Ingresa un correo electrónico válido.', 'codigo' => ''];
        if (empty($asientoIds))
            return ['exito' => false, 'mensaje' => 'Debes seleccionar al menos un asiento.', 'codigo' => ''];
        if (count($asientoIds) > 6)
            return ['exito' => false, 'mensaje' => 'No puedes reservar más de 6 asientos a la vez.', 'codigo' => ''];

        $asientoIds = array_values(array_filter(array_map('intval', $asientoIds), fn($id) => $id > 0));
        $pdo = obtenerConexion();

        try {
            $pdo->beginTransaction();

            // Bloquear la función para lectura exclusiva
            $stmtFuncion = $pdo->prepare("
                SELECT id, sala_id, precio FROM funciones
                WHERE id = :fid AND activa = 1 AND fecha_hora > NOW()
                FOR UPDATE
            ");
            $stmtFuncion->execute([':fid' => $funcionId]);
            $funcion = $stmtFuncion->fetch();

            if (!$funcion) {
                $pdo->rollBack();
                return ['exito' => false, 'mensaje' => 'La función no está disponible o ya finalizó.', 'codigo' => ''];
            }

            // Bloquear los asientos solicitados para evitar condición de carrera
            $placeholders = implode(',', array_map(fn($i) => ":a{$i}", array_keys($asientoIds)));
            $params = [':fid' => $funcionId, ':sala_id' => $funcion['sala_id']];
            foreach ($asientoIds as $i => $id) $params[":a{$i}"] = $id;

            $stmtBloqueo = $pdo->prepare("
                SELECT a.id AS asiento_id, a.fila, a.numero, a.sala_id,
                    (SELECT COUNT(*) FROM reservas r
                     WHERE r.asiento_id = a.id AND r.funcion_id = :fid
                       AND r.estado IN ('pendiente','confirmada')) AS ya_reservado
                FROM asientos a
                WHERE a.id IN ({$placeholders}) AND a.sala_id = :sala_id
                FOR UPDATE
            ");
            $stmtBloqueo->execute($params);
            $asientosVerificados = $stmtBloqueo->fetchAll();

            if (count($asientosVerificados) !== count($asientoIds)) {
                $pdo->rollBack();
                return ['exito' => false, 'mensaje' => 'Uno o más asientos no pertenecen a esta función.', 'codigo' => ''];
            }

            $ocupados = [];
            foreach ($asientosVerificados as $a) {
                if ((int)$a['ya_reservado'] > 0) $ocupados[] = $a['fila'] . $a['numero'];
            }
            if (!empty($ocupados)) {
                $pdo->rollBack();
                return [
                    'exito'   => false,
                    'mensaje' => 'Los asientos ' . implode(', ', $ocupados) . ' ya están reservados. Elige otros.',
                    'codigo'  => '',
                ];
            }

            $codigo     = self::GenerarCodigoDeConfirmacion();
            $expiracion = date('Y-m-d H:i:s', strtotime('+' . RESERVA_TIEMPO_LIMITE . ' minutes'));

            $stmtInsert = $pdo->prepare("
                INSERT INTO reservas (funcion_id, asiento_id, nombre_cliente, email_cliente, estado, fecha_expiracion, codigo_reserva)
                VALUES (:fid, :asiento_id, :nombre, :email, 'confirmada', :expira, :codigo)
            ");
            foreach ($asientosVerificados as $asiento) {
                $stmtInsert->execute([
                    ':fid'        => $funcionId,
                    ':asiento_id' => $asiento['asiento_id'],
                    ':nombre'     => trim($nombreCliente),
                    ':email'      => strtolower(trim($email)),
                    ':expira'     => $expiracion,
                    ':codigo'     => $codigo,
                ]);
            }

            $pdo->commit();

            $this->idFuncion     = $funcionId;
            $this->nombreCliente = trim($nombreCliente);
            $this->email         = strtolower(trim($email));
            $this->codigoReserva = $codigo;
            $this->estado        = 'confirmada';

            return ['exito' => true, 'mensaje' => '¡Reserva confirmada!', 'codigo' => $codigo];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            registrarError('Reserva::CrearReserva', $e->getMessage());
            if ($e->getCode() === '23000')
                return ['exito' => false, 'mensaje' => 'Un asiento fue tomado en el último momento. Por favor elige otros.', 'codigo' => ''];
            return ['exito' => false, 'mensaje' => 'Error interno. Por favor inténtalo de nuevo.', 'codigo' => ''];
        }
    }

    /** Código único con formato CF-XXXXXX (RN-05), sin caracteres ambiguos (0/O, 1/I). */
    public static function GenerarCodigoDeConfirmacion(): string
    {
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = 'CF-';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $codigo;
    }

    /** Reservas de un cliente por email, de más reciente a más antigua. */
    public static function ObtenerPorEmail(string $email): array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT r.id AS reserva_id, r.codigo_reserva, r.estado, r.fecha_reserva, r.fecha_expiracion,
                    r.nombre_cliente, r.email_cliente,
                    a.fila, a.numero AS asiento_numero, a.tipo AS tipo_asiento,
                    f.fecha_hora, f.precio, f.idioma,
                    p.titulo AS pelicula, p.imagen AS poster,
                    s.nombre AS sala
                FROM reservas r
                INNER JOIN asientos  a ON a.id = r.asiento_id
                INNER JOIN funciones f ON f.id = r.funcion_id
                INNER JOIN peliculas p ON p.id = f.pelicula_id
                INNER JOIN salas     s ON s.id = f.sala_id
                WHERE r.email_cliente = :email
                ORDER BY r.fecha_reserva DESC
            ");
            $stmt->execute([':email' => strtolower(trim($email))]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            registrarError('Reserva::ObtenerPorEmail', $e->getMessage());
            return [];
        }
    }

    /** Reserva por su código único (ej: "CF-A3X9KW"); una fila por asiento. */
    public static function ObtenerPorCodigo(string $codigo): ?array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT r.id AS reserva_id, r.codigo_reserva, r.estado, r.fecha_reserva, r.fecha_expiracion,
                    r.nombre_cliente, r.email_cliente,
                    a.fila, a.numero AS asiento_numero, a.tipo AS tipo_asiento,
                    f.id AS funcion_id, f.fecha_hora, f.precio, f.idioma,
                    p.titulo AS pelicula, p.clasificacion, p.imagen AS poster,
                    s.nombre AS sala, s.tipo AS tipo_sala
                FROM reservas r
                INNER JOIN asientos  a ON a.id = r.asiento_id
                INNER JOIN funciones f ON f.id = r.funcion_id
                INNER JOIN peliculas p ON p.id = f.pelicula_id
                INNER JOIN salas     s ON s.id = f.sala_id
                WHERE r.codigo_reserva = :codigo
                ORDER BY a.fila ASC, a.numero ASC
            ");
            $stmt->execute([':codigo' => strtoupper(trim($codigo))]);
            $filas = $stmt->fetchAll();
            return empty($filas) ? null : $filas;
        } catch (PDOException $e) {
            registrarError('Reserva::ObtenerPorCodigo', $e->getMessage());
            return null;
        }
    }
}
