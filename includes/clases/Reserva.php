<?php
/**
 * Clase Reserva
 * Encapsula la lógica de negocio para crear y consultar reservas.
 * Una reserva puede tener múltiples asientos (mismo código de reserva).
 */
class Reserva
{
    public int    $id;
    public string $nombreCliente;
    public string $email;
    public int    $idFuncion;

    // Propiedades extendidas
    public string  $codigoReserva  = '';
    public string  $estado         = 'confirmada';
    public string  $fechaReserva   = '';
    public string  $fechaExpiracion = '';

    public function __construct(array $fila)
    {
        $this->id             = (int)($fila['id']             ?? 0);
        $this->nombreCliente  = $fila['nombre_cliente']        ?? '';
        $this->email          = $fila['email_cliente']         ?? '';
        $this->idFuncion      = (int)($fila['funcion_id']     ?? 0);
        $this->codigoReserva  = $fila['codigo_reserva']        ?? '';
        $this->estado         = $fila['estado']                ?? 'confirmada';
        $this->fechaReserva   = $fila['fecha_reserva']         ?? '';
        $this->fechaExpiracion = $fila['fecha_expiracion']     ?? '';
    }

    /**
     * Crea una reserva con uno o varios asientos.
     * Valida disponibilidad, ejecuta la transacción y genera el código.
     *
     * @param  int   $funcionId
     * @param  int[] $asientoIds    IDs de los asientos seleccionados
     * @param  string $nombreCliente
     * @param  string $email
     * @return array  ['exito'=>bool, 'mensaje'=>string, 'codigo'=>string]
     */
    public function CrearReserva(int $funcionId, array $asientoIds,
                                  string $nombreCliente, string $email): array
    {
        // Validaciones básicas
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

        $asientoIds = array_values(array_filter(
            array_map('intval', $asientoIds),
            fn($id) => $id > 0
        ));

        $pdo = obtenerConexion();

        try {
            $pdo->beginTransaction();

            // Bloquear la función para lectura exclusiva
            $stmtFuncion = $pdo->prepare("
                SELECT id, sala_id, precio
                FROM   funciones
                WHERE  id      = :fid
                  AND  activa  = 1
                  AND  fecha_hora > NOW()
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
            $sqlBloqueo = "
                SELECT
                    a.id AS asiento_id,
                    a.fila,
                    a.numero,
                    a.sala_id,
                    (
                        SELECT COUNT(*) FROM reservas r
                        WHERE  r.asiento_id = a.id
                          AND  r.funcion_id  = :fid
                          AND  r.estado IN ('pendiente','confirmada')
                    ) AS ya_reservado
                FROM asientos a
                WHERE a.id IN ({$placeholders}) AND a.sala_id = :sala_id
                FOR UPDATE
            ";
            $params = [':fid' => $funcionId, ':sala_id' => $funcion['sala_id']];
            foreach ($asientoIds as $i => $id) $params[":a{$i}"] = $id;

            $stmtBloqueo = $pdo->prepare($sqlBloqueo);
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
                INSERT INTO reservas
                    (funcion_id, asiento_id, nombre_cliente, email_cliente,
                     estado, fecha_expiracion, codigo_reserva)
                VALUES
                    (:fid, :asiento_id, :nombre, :email,
                     'confirmada', :expira, :codigo)
            ");

            foreach ($asientosVerificados as $asiento) {
                $stmtInsert->execute([
                    ':fid'       => $funcionId,
                    ':asiento_id' => $asiento['asiento_id'],
                    ':nombre'    => trim($nombreCliente),
                    ':email'     => strtolower(trim($email)),
                    ':expira'    => $expiracion,
                    ':codigo'    => $codigo,
                ]);
            }

            $pdo->commit();

            // Actualizar propiedades de la instancia
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

    /**
     * Genera un código de confirmación único con el formato CF-XXXXXX.
     * Usa caracteres sin ambigüedad visual (sin 0/O, 1/I).
     *
     * @return string  Ej: "CF-A3X9KW"
     */
    public static function GenerarCodigoDeConfirmacion(): string
    {
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = 'CF-';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $codigo;
    }

    /**
     * Obtiene todas las reservas de un cliente por email,
     * ordenadas de más reciente a más antigua.
     *
     * @param  string $email
     * @return array
     */
    public static function ObtenerPorEmail(string $email): array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    r.id               AS reserva_id,
                    r.codigo_reserva,
                    r.estado,
                    r.fecha_reserva,
                    r.fecha_expiracion,
                    r.nombre_cliente,
                    r.email_cliente,
                    a.fila,
                    a.numero           AS asiento_numero,
                    a.tipo             AS tipo_asiento,
                    f.fecha_hora,
                    f.precio,
                    f.idioma,
                    p.titulo           AS pelicula,
                    p.imagen           AS poster,
                    s.nombre           AS sala
                FROM   reservas r
                INNER JOIN asientos  a ON a.id = r.asiento_id
                INNER JOIN funciones f ON f.id = r.funcion_id
                INNER JOIN peliculas p ON p.id = f.pelicula_id
                INNER JOIN salas     s ON s.id = f.sala_id
                WHERE  r.email_cliente = :email
                ORDER  BY r.fecha_reserva DESC
            ");
            $stmt->execute([':email' => strtolower(trim($email))]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            registrarError('Reserva::ObtenerPorEmail', $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene una reserva por su código único (ej: "CF-A3X9KW").
     * Devuelve array de filas (una por asiento) o null si no existe.
     *
     * @param  string $codigo
     * @return array|null
     */
    public static function ObtenerPorCodigo(string $codigo): ?array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    r.id               AS reserva_id,
                    r.codigo_reserva,
                    r.estado,
                    r.fecha_reserva,
                    r.fecha_expiracion,
                    r.nombre_cliente,
                    r.email_cliente,
                    a.fila,
                    a.numero           AS asiento_numero,
                    a.tipo             AS tipo_asiento,
                    f.id               AS funcion_id,
                    f.fecha_hora,
                    f.precio,
                    f.idioma,
                    p.titulo           AS pelicula,
                    p.clasificacion,
                    p.imagen           AS poster,
                    s.nombre           AS sala,
                    s.tipo             AS tipo_sala
                FROM   reservas r
                INNER JOIN asientos  a ON a.id = r.asiento_id
                INNER JOIN funciones f ON f.id = r.funcion_id
                INNER JOIN peliculas p ON p.id = f.pelicula_id
                INNER JOIN salas     s ON s.id = f.sala_id
                WHERE  r.codigo_reserva = :codigo
                ORDER  BY a.fila ASC, a.numero ASC
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
