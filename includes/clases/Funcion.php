<?php
/**
 * Clase Funcion
 * Representa una función (proyección) de una película en una sala,
 * a una fecha y hora determinadas.
 *
 * Relaciones (UML):
 *   - Agrega  Pelicula  (idPelicula)
 *   - Agrega  Sala      (idSala)
 *   - Compone Asiento[] (asientos de la sala para esa función)
 */
class Funcion
{
    public int    $id;
    public int    $idPelicula;
    public int    $idSala;
    public string $horario;   // fecha_hora ISO: "2025-06-01 20:30:00"
    public float  $precio;

    // Propiedades extendidas
    public string  $idioma        = 'castellano';
    public bool    $activa        = true;
    public ?string $peliculaTitulo = null;
    public ?string $salaNombre    = null;
    public int     $asientosLibres = 0;

    public function __construct(array $fila)
    {
        $this->id            = (int)$fila['id'];
        $this->idPelicula    = (int)($fila['pelicula_id'] ?? 0);
        $this->idSala        = (int)($fila['sala_id']     ?? 0);
        $this->horario       = $fila['fecha_hora']         ?? '';
        $this->precio        = (float)($fila['precio']    ?? 0);
        $this->idioma        = $fila['idioma']             ?? 'castellano';
        $this->activa        = (bool)($fila['activa']     ?? true);
        $this->peliculaTitulo = $fila['pelicula_titulo']  ?? null;
        $this->salaNombre     = $fila['sala_nombre']       ?? null;
        $this->asientosLibres = (int)($fila['asientos_libres'] ?? 0);
    }

    /**
     * Verifica si todos los asientos solicitados están disponibles
     * para esta función (sin reserva activa).
     *
     * @param  int[] $asientoIds  IDs de asientos a verificar
     * @return bool
     */
    public function ValidarDisponibilidadAsientos(array $asientoIds): bool
    {
        if (empty($asientoIds)) return false;

        $pdo = obtenerConexion();
        try {
            $placeholders = implode(',', array_fill(0, count($asientoIds), '?'));
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS ocupados
                FROM   reservas
                WHERE  funcion_id  = ?
                  AND  asiento_id IN ({$placeholders})
                  AND  estado IN ('pendiente','confirmada')
            ");
            $params = array_merge([$this->id], array_values($asientoIds));
            $stmt->execute($params);
            $fila = $stmt->fetch();
            return (int)$fila['ocupados'] === 0;
        } catch (PDOException $e) {
            registrarError('Funcion::ValidarDisponibilidadAsientos', $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene los horarios disponibles de una película para los
     * próximos 7 días.
     *
     * @param  int $peliculaId
     * @return array  Filas con horario, sala, precio, idioma, asientos_libres
     */
    public static function ObtenerHorarios(int $peliculaId): array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    f.id,
                    f.pelicula_id,
                    f.sala_id,
                    f.fecha_hora,
                    f.precio,
                    f.idioma,
                    s.nombre       AS sala_nombre,
                    s.tipo         AS sala_tipo,
                    (
                        SELECT COUNT(*) FROM asientos a WHERE a.sala_id = f.sala_id
                    ) - (
                        SELECT COUNT(*) FROM reservas r
                        WHERE  r.funcion_id = f.id
                          AND  r.estado IN ('pendiente','confirmada')
                    ) AS asientos_libres
                FROM funciones f
                INNER JOIN salas s ON s.id = f.sala_id
                WHERE f.pelicula_id = :pelicula_id
                  AND f.activa      = 1
                  AND f.fecha_hora  BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY f.fecha_hora ASC
            ");
            $stmt->execute([':pelicula_id' => $peliculaId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            registrarError('Funcion::ObtenerHorarios', $e->getMessage());
            return [];
        }
    }

    /**
     * Marca un asiento como ocupado para esta función insertando
     * una reserva de bloqueo temporal.
     * Se usa en flujos de selección con transacción activa.
     *
     * @param  int $asientoId
     * @return bool
     */
    public function MarcarAsientoOcupado(int $asientoId): bool
    {
        $pdo = obtenerConexion();
        try {
            // Verificar que el asiento esté libre antes de marcarlo
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) AS c FROM reservas
                WHERE funcion_id = :fid AND asiento_id = :aid
                  AND estado IN ('pendiente','confirmada')
            ");
            $stmtCheck->execute([':fid' => $this->id, ':aid' => $asientoId]);
            if ((int)$stmtCheck->fetch()['c'] > 0) return false;

            $stmt = $pdo->prepare("
                UPDATE asientos SET tipo = tipo WHERE id = :id AND sala_id = :sala_id
            ");
            $stmt->execute([':id' => $asientoId, ':sala_id' => $this->idSala]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            registrarError('Funcion::MarcarAsientoOcupado', $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene una función por su ID con datos de película y sala.
     *
     * @param  int $id
     * @return self|null
     */
    public static function ObtenerPorId(int $id): ?self
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    f.id,
                    f.pelicula_id,
                    f.sala_id,
                    f.fecha_hora,
                    f.precio,
                    f.idioma,
                    f.activa,
                    p.titulo       AS pelicula_titulo,
                    p.clasificacion,
                    p.duracion_min,
                    p.imagen,
                    s.nombre       AS sala_nombre,
                    s.tipo         AS sala_tipo,
                    (
                        SELECT COUNT(*) FROM asientos a WHERE a.sala_id = f.sala_id
                    ) - (
                        SELECT COUNT(*) FROM reservas r
                        WHERE  r.funcion_id = f.id
                          AND  r.estado IN ('pendiente','confirmada')
                    ) AS asientos_libres
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
