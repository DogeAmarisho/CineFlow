<?php
/**
 * Clase Sala
 * Representa una sala de cine con su capacidad de asientos.
 */
class Sala
{
    public int    $id;
    public int    $numAsientos;  // total de asientos de la sala

    // Propiedades extendidas
    public string $nombre = '';
    public string $tipo   = '';  // estandar, vip, 4dx, imax

    public function __construct(array $fila)
    {
        $this->id          = (int)$fila['id'];
        $this->numAsientos = (int)($fila['num_asientos'] ?? 0);
        $this->nombre      = $fila['nombre'] ?? '';
        $this->tipo        = $fila['tipo']   ?? '';
    }

    /**
     * Obtiene una sala por su ID.
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
                    s.id,
                    s.nombre,
                    s.tipo,
                    COUNT(a.id) AS num_asientos
                FROM salas s
                LEFT JOIN asientos a ON a.sala_id = s.id
                WHERE s.id = :id
                GROUP BY s.id, s.nombre, s.tipo
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch();
            return $fila ? new self($fila) : null;
        } catch (PDOException $e) {
            registrarError('Sala::ObtenerPorId', $e->getMessage());
            return null;
        }
    }

    /**
     * Lista todas las salas con su conteo de asientos.
     *
     * @return self[]
     */
    public static function ListarTodas(): array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->query("
                SELECT
                    s.id,
                    s.nombre,
                    s.tipo,
                    COUNT(a.id) AS num_asientos
                FROM salas s
                LEFT JOIN asientos a ON a.sala_id = s.id
                GROUP BY s.id, s.nombre, s.tipo
                ORDER BY s.id ASC
            ");
            return array_map(fn($fila) => new self($fila), $stmt->fetchAll());
        } catch (PDOException $e) {
            registrarError('Sala::ListarTodas', $e->getMessage());
            return [];
        }
    }
}
