<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Availability_Rules_Service
 *
 * Capa central para reglas globales de disponibilidad.
 * - get(): obtiene una regla (tipada) con default
 * - all(): obtiene todas las reglas (tipadas)
 * - save(): guarda y refresca cache
 * - delete(): borra y refresca cache
 */
class BB_Availability_Rules_Service {

    private BB_Availability_Rules_Repo $repo;

    /** @var array<string,mixed> */
    private array $mem = array();

    private bool $all_loaded = false;

    // Cache persistente (opcional)
    private string $cache_group = 'bb_availability';
    private string $cache_key_all = 'global_rules_all_v1';
    private int $cache_ttl = 300; // 5 min

    public function __construct( BB_Availability_Rules_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Get one rule (typed).
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        $key = sanitize_key( $key );
        if ( $key === '' ) return $default;

        // request cache
        if ( array_key_exists( $key, $this->mem ) ) {
            $v = $this->mem[ $key ];
            return ( $v === null || $v === '' ) ? $default : $v;
        }

        // if all already loaded, missing -> default
        if ( $this->all_loaded ) {
            return $default;
        }

        $raw = $this->repo->get_rule( $key );
        if ( $raw === null ) {
            $this->mem[ $key ] = null;
            return $default;
        }

        $typed = $this->decode_raw( $raw );
        $this->mem[ $key ] = $typed;

        return ( $typed === null || $typed === '' ) ? $default : $typed;
    }

    /**
     * Get all rules as [key => typed_value]
     */
    public function all(): array {

        if ( $this->all_loaded ) {
            return $this->mem;
        }

        // wp_cache (optional)
        $cached = wp_cache_get( $this->cache_key_all, $this->cache_group );
        if ( is_array( $cached ) ) {
            $this->mem = $cached;
            $this->all_loaded = true;
            return $this->mem;
        }

        $raw_all = $this->repo->get_all();

        $out = array();
        foreach ( $raw_all as $k => $raw ) {
            $out[ $k ] = $this->decode_raw( $raw );
        }

        $this->mem = $out;
        $this->all_loaded = true;

        wp_cache_set( $this->cache_key_all, $this->mem, $this->cache_group, $this->cache_ttl );

        return $this->mem;
    }

    /**
     * Save a rule, refresh caches.
     *
     * @param string $key
     * @param mixed $value
     */
    public function save( string $key, $value ): bool {
        $key = sanitize_key( $key );
        if ( $key === '' ) return false;

        $ok = $this->repo->save_rule( $key, $value );

        if ( $ok ) {
            // Keep cache consistent with read behavior
            $this->mem[ $key ] = $this->normalize_typed( $value );
            $this->all_loaded = false;
            $this->flush_cache();
        }

        return $ok;
    }

    /**
     * Delete a rule, refresh caches.
     */
    public function delete( string $key ): bool {
        $key = sanitize_key( $key );
        if ( $key === '' ) return false;

        $ok = $this->repo->delete_rule( $key );

        if ( $ok ) {
            unset( $this->mem[ $key ] );
            $this->all_loaded = false;
            $this->flush_cache();
        }

        return $ok;
    }

    /**
     * --------------------
     * Helpers
     * --------------------
     */

    private function flush_cache(): void {
        wp_cache_delete( $this->cache_key_all, $this->cache_group );
    }

    /**
     * Decode raw DB string to typed value:
     * - JSON arrays/objects -> array
     * - numeric strings -> int/float
     * - others -> string
     */
    private function decode_raw( ?string $raw ) {
        if ( $raw === null ) return null;

        $raw = trim( (string) $raw );
        if ( $raw === '' ) return '';

        // Looks like JSON?
        $first = substr( $raw, 0, 1 );
        $last  = substr( $raw, -1 );

        if ( ( $first === '{' && $last === '}' ) || ( $first === '[' && $last === ']' ) ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $decoded;
            }
        }

        // Numeric?
        if ( is_numeric( $raw ) ) {
            return ( strpos( $raw, '.' ) !== false ) ? (float) $raw : (int) $raw;
        }

        return $raw;
    }

    /**
     * Normalize written values to match read types.
     */
    private function normalize_typed( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) return $value;
        if ( $value === null ) return null;

        if ( is_bool( $value ) ) {
            // Decide bool storage style for reads (you can change to true/false if you want)
            return $value ? 1 : 0;
        }

        if ( is_string( $value ) ) {
            $v = trim( $value );
            if ( $v !== '' && is_numeric( $v ) ) {
                return ( strpos( $v, '.' ) !== false ) ? (float) $v : (int) $v;
            }
            return $value;
        }

        return $value;
    }
}
