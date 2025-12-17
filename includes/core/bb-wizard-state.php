<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manejador del estado del wizard (tipo "carrito").
 *
 * Guarda todo en $_SESSION['bb_wizard_state'] y permite:
 *  - Uso por instancia: $this->state->set_step_data(...), get_all(), etc.
 *  - Uso estático heredado: Bubbles_Wizard_State::get_state(), add_vehicle(), etc.
 */



class BB_Wizard_State {

    const SESSION_KEY = 'bb_wizard_state';

    protected static function &ref() {
        if ( ! isset( $_SESSION ) || ! is_array( $_SESSION ) ) {
            $_SESSION = array();
        }

        if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) || ! is_array( $_SESSION[ self::SESSION_KEY ] ) ) {
            $_SESSION[ self::SESSION_KEY ] = array();
        }

        return $_SESSION[ self::SESSION_KEY ];
    }

    public function __construct() {
        error_log("⚡ BB_Wizard_State -> constructor ejecutado");
        self::ref();
    }

    public function set_step_data( $step, array $data ) {
        $state = & self::ref();
        $state[ $step ] = $data;
        error_log("📝 BB_Wizard_State::set_step_data($step): " . json_encode($data));
    }

    public function get_all() {
        $state = & self::ref();
        error_log("📦 BB_Wizard_State::get_all() = " . json_encode($state));
        return $state;
    }

    // ... resto de métodos ...


    /**
     * Limpia todo el estado
     */
    public static function reset() {
        $_SESSION[ self::SESSION_KEY ] = array();
    }

    /* ----------------------------------------------------------
     * MÉTODOS ESTÁTICOS (compatibles con código existente)
     * ---------------------------------------------------------- */

    /**
     * Devuelve todo el estado (equivalente a get_all())
     *
     * @return array
     */
    public static function get_state() {
        $state = & self::ref();
        return $state;
    }

    /**
     * Añade un vehículo al “carrito”
     *
     * @param array $vehicle
     */
    public static function add_vehicle( array $vehicle ) {
        $state = & self::ref();

        if ( ! isset( $state['vehicles'] ) || ! is_array( $state['vehicles'] ) ) {
            $state['vehicles'] = array();
        }

        $state['vehicles'][] = $vehicle;
    }

    /**
     * Devuelve la lista de vehículos
     *
     * @return array
     */
    public static function get_vehicles() {
        $state = & self::ref();

        if ( isset( $state['vehicles'] ) && is_array( $state['vehicles'] ) ) {
            return $state['vehicles'];
        }

        return array();
    }

    /**
     * Guarda dirección en el estado
     *
     * @param array $address
     */
    public static function set_address( array $address ) {
        $state = & self::ref();
        $state['address'] = $address;
    }

    /**
     * Guarda fecha y hora en el estado
     *
     * @param string $date
     * @param string $time
     */
    public static function set_date_time( $date, $time ) {
        $state = & self::ref();
        $state['date'] = array(
            'date' => (string) $date,
            'time' => (string) $time,
        );
    }

    /**
     * Alias para resetear todo (por si algún código llamaba reset_all())
     */
    public static function reset_all() {
        self::reset();
    }
}
