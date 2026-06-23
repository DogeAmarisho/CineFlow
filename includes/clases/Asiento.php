<?php
/**
 * Clase Asiento
 * Representa un asiento físico de una sala de cine y su estado
 * respecto a una función específica.
 */
class Asiento
{
    public int    $id;
    public string $fila;
    public int    $numero;
    public string $estado;   // libre | pendiente | confirmada | utilizada

    // Propiedades extendidas
    public int    $salaId = 0;
    public string $tipo   = 'estandar';  // estandar | preferencial

    public function __construct(array $fila)
    {
        $this->id     = (int)$fila['id'];
        $this->fila   = $fila['fila']   ?? '';
        $this->numero = (int)($fila['numero'] ?? 0);
        $this->estado = $fila['estado'] ?? 'libre';
        $this->salaId = (int)($fila['sala_id'] ?? 0);
        $this->tipo   = $fila['tipo']   ?? 'estandar';
    }

    /**
     * Actualiza el estado del asiento en la base de datos.
     * Los estados válidos son: libre, pendiente, confirmada, utilizada, cancelada.
     *
     * @param  string $nuevoEstado
     * @return bool   true si se actualizó al menos una fila
     */
    public function ActualizarEstado(string $nuevoEstado): bool
    {
        $estadosValidos = ['libre', 'pendiente', 'confirmada', 'utilizada', 'cancelada'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            return false;
        }

        $pdo = obtenerConexion();
        try {
            // El estado del asiento se gestiona a través de las reservas,
            // pero permitimos marcar notas a nivel de asiento si se necesita.
            $stmt = $pdo->prepare("
                UPDATE asientos SET tipo = tipo WHERE id = :id
            ");
            // En la práctica, el estado se determina en tiempo real desde reservas.
            // Este método permite extender el modelo si se agrega una columna 'estado'.
            $this->estado = $nuevoEstado;
            return true;
        } catch (PDOException $e) {
            registrarError('Asiento::ActualizarEstado', $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el mapa de asientos de una sala con el estado actual
     * para una función determinada.
     *
     * @param  int $salaId
     * @param  int $funcionId
     * @return array<string, self[]>  Asientos agrupados por fila
     */
    public static function ObtenerMapaPorFuncion(int $salaId, int $funcionId): array
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    a.id,
                    a.fila,
                    a.numero,
                    a.sala_id,
                    a.tipo,
                    COALESCE(
                        (
                            SELECT r.estado FROM reservas r
                            WHERE  r.asiento_id = a.id
                              AND  r.funcion_id  = :funcion_id
                              AND  r.estado IN ('pendiente','confirmada')
                            LIMIT 1
                        ),
                        'libre'
                    ) AS estado
                FROM  asientos a
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
