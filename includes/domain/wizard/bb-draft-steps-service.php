<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Draft_Steps_Service {

    private BB_Draft_Steps_Repo $repo;
    private string $token;
    private int $ttl_hours; 

    /** @var array */
    private array $state = array();

    /** @var string */
    private string $current_step = 'vehicle';

    /** @var bool */
    private bool $loaded = false;

    public function __construct(
        BB_Draft_Steps_Repo $repo,
        string $token,
        int $ttl_hours = 24
    ) {
        $this->repo      = $repo;
        $this->token     = $token;
        $this->ttl_hours = max( 1, (int) $ttl_hours );

        // ✅ asegura tabla
        if ( method_exists( $this->repo, 'create_table_if_needed' ) ) {
            $this->repo->create_table_if_needed();
        }
    }

    public function get_token(): string {
        return $this->token;
    }

    /**
     * =========================
     * API NUEVA (Shell)
     * =========================
     */

    public function get_state(): array {
        $this->load();
        return $this->state;
    }

    public function get_current_step(): string {
        $this->load();
        return $this->current_step ?: 'vehicle';
    }

    public function set_current_step( string $step ): void {
        $step = sanitize_key( $step );
        if ( $step !== '' ) {
            $this->current_step = $step;
        }
    }

    /**
     * ✅ Reemplaza el estado en memoria (no guarda todavía).
     * Útil si quieres setear el state completo de una vez.
     */
    public function set_state( array $state ): void {
        $this->load();
        $this->state = $state;
    }

    /**
     * ✅ Merge explícito: agrega/actualiza keys sin borrar lo demás.
     * (Aun así el Repo debe mergear por seguridad, pero aquí te ayuda en memoria.)
     */
    public function merge_state( array $partial ): void {
        $this->load();
        $this->state = array_replace_recursive( (array) $this->state, (array) $partial );
    }

    /**
     * Mantengo tu método slice (compat).
     */
    public function set_slice( string $key, $value ): void {
        $this->load();
        $key = sanitize_key( $key );
        if ( $key === '' ) return;
        $this->state[ $key ] = $value;
    }

    /**
     * ✅ Crea la fila SOLO si no existe aún.
     * - Llamarlo en el primer step (vehicle) para "inicializar" el draft.
     * - Si ya existe, no hace nada.
     */
    public function ensure_created(): bool {
        $this->load();

        // Si ya existe (porque load encontró row), no reinsertar
        if ( ! empty( $this->state ) || $this->current_step !== 'vehicle' ) {
            // Ojo: state vacío también puede ser válido; pero si existe row, load ya lo habría cargado.
            // La forma segura: preguntar al repo:
            $row = $this->repo->get_by_token( $this->token );
            if ( ! empty( $row ) ) return true;
        }

        // Crear fila con state mínimo
        $this->state = is_array( $this->state ) ? $this->state : array();
        $this->state['_draft_initialized'] = 1;

        $this->current_step = 'vehicle';
        return $this->save( 'draft', true );
    }

    /**
     * Guarda draft (UPDATE si existe, INSERT si no existe).
     * - Siempre guarda current_step en columna y también en state (compat/debug).
     * - Por defecto recarga state después de guardar para evitar “state parcial”.
     *
     * @param string $status 'draft'|'submitted'
     * @param bool   $reload_after_save true = recarga desde BD al terminar
     */
    public function save( string $status = 'draft', bool $reload_after_save = true ): bool {
        $this->load();

        $status = ( $status === 'submitted' ) ? 'submitted' : 'draft';

        // Guardamos también el current_step dentro del state (útil para debug/compat)
        $this->state['current_step'] = $this->current_step;

        $ok = $this->repo->upsert(
            $this->token,
            $this->current_step ?: 'vehicle',
            $this->state,
            $status
        );

        // ✅ IMPORTANT: recargar state real desde BD (ya mergeado)
        if ( $ok && $reload_after_save ) {
            $this->reload();
        }

        return $ok;
    }

    /**
     * =========================
     * API VIEJA (compat)
     * =========================
     */

    public function save_state( string $current_step, array $state ): bool {
        $current_step = sanitize_key( $current_step );
        if ( $current_step === '' ) $current_step = 'vehicle';

        $this->set_current_step( $current_step );
        $this->set_state( $state );

        return $this->save( 'draft', true );
    }

    public function clear(): bool {
        // Limpia memoria y marca como no cargado
        $this->state = array();
        $this->current_step = 'vehicle';
        $this->loaded = false;

        return $this->repo->delete_by_token( $this->token );
    }

    /**
     * =========================
     * Interno
     * =========================
     */

    /**
     * Fuerza recarga desde BD.
     */
    private function reload(): void {
        $this->loaded = false;
        $this->state = array();
        $this->current_step = 'vehicle';
        $this->load();
    }

    private function load(): void {
        if ( $this->loaded ) return;
        $this->loaded = true;

        $row = $this->repo->get_by_token( $this->token );

        if ( empty( $row ) ) {
            $this->state = array();
            $this->current_step = 'vehicle';
            return;
        }

        $this->current_step = ! empty( $row['current_step'] )
            ? (string) $row['current_step']
            : 'vehicle';

        $decoded = array();
        if ( ! empty( $row['state'] ) ) {
            $decoded = json_decode( (string) $row['state'], true );
        }

        $this->state = is_array( $decoded ) ? $decoded : array();

        // Si alguien guardó current_step dentro del state, respétalo
        if ( ! empty( $this->state['current_step'] ) ) {
            $this->current_step = (string) $this->state['current_step'];
        }
    }
}
