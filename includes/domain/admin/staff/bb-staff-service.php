<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Staff_Service
 *
 * Admin governance for bb_staff users:
 * - List staff users
 * - Read current approval status
 * - Approve / Reject
 * - Build rows for admin table (includes helpful profile snapshot)
 */
final class BB_Staff_Service {

    private TechProfileRepo $profile_repo;

    public function __construct( TechProfileRepo $profile_repo ) {
        $this->profile_repo = $profile_repo;
    }

    /**
     * List all staff users (role: bb_staff)
     *
     * @return WP_User[]
     */
    public function list_staff_users(): array {

        $q = new WP_User_Query( array(
            'role'    => 'bb_staff',
            'orderby' => 'registered',
            'order'   => 'DESC',
            'number'  => -1,
        ) );

        $users = $q->get_results();
        return is_array( $users ) ? $users : array();
    }

    /**
     * pending | approved | rejected
     */
    public function get_status( int $user_id ): string {
        $v = (string) get_user_meta( $user_id, 'bb_tech_status', true );
        $v = trim( $v );
        return $v !== '' ? $v : 'pending';
    }

    public function approve( int $tech_id, int $admin_id ): void {

        update_user_meta( $tech_id, 'bb_tech_status', 'approved' );

        update_user_meta( $tech_id, 'bb_tech_approved_at', current_time( 'mysql' ) );
        update_user_meta( $tech_id, 'bb_tech_approved_by', (int) $admin_id );

        delete_user_meta( $tech_id, 'bb_tech_rejected_at' );
        delete_user_meta( $tech_id, 'bb_tech_rejected_by' );
        delete_user_meta( $tech_id, 'bb_tech_rejection_reason' );
    }

    public function reject( int $tech_id, int $admin_id, string $reason = '' ): void {

        update_user_meta( $tech_id, 'bb_tech_status', 'rejected' );

        update_user_meta( $tech_id, 'bb_tech_rejected_at', current_time( 'mysql' ) );
        update_user_meta( $tech_id, 'bb_tech_rejected_by', (int) $admin_id );

        $reason = trim( wp_strip_all_tags( $reason ) );
        if ( $reason !== '' ) {
            update_user_meta( $tech_id, 'bb_tech_rejection_reason', $reason );
        } else {
            delete_user_meta( $tech_id, 'bb_tech_rejection_reason' );
        }
    }

    /**
     * ✅ Admin rows with helpful info for approval decision.
     */
    public function build_staff_rows(): array {

        $users = $this->list_staff_users();
        $rows  = array();

        foreach ( $users as $u ) {

            $tech_id = (int) $u->ID;
            $p       = $this->profile_repo->get_profile( $tech_id );

            $address = ( isset($p['address']) && is_array($p['address']) ) ? $p['address'] : array();
            $geo     = ( isset($p['geo']) && is_array($p['geo']) ) ? $p['geo'] : array();

            $langs = isset($p['languages']) && is_array($p['languages']) ? $p['languages'] : array();
            $langs = array_values(array_filter(array_map('sanitize_text_field', $langs)));

            $lat = (string)($geo['lat'] ?? '');
            $lng = (string)($geo['lng'] ?? '');

            $rows[] = array(
                'id'     => $tech_id,
                'name'   => trim( $u->display_name ) !== '' ? $u->display_name : ( $u->user_login ?? ('User #' . $tech_id) ),
                'email'  => (string) $u->user_email,

                // profile snapshot
                'phone'     => (string)($p['phone'] ?? ''),
                'bio'       => (string)($p['bio'] ?? ''),
                'languages' => $langs,
                'avatar_id' => (int)($p['avatar_id'] ?? 0),

                // governance
                'status' => $this->get_status( $tech_id ),

                // service area (informative)
                'radius'  => (string)($p['radius'] ?? ''),
                'address' => (string)($address['formatted'] ?? ''),

                // geo
                'geo_lat'    => $lat,
                'geo_lng'    => $lng,
                'geo_status' => (string)($geo['status'] ?? ''),
                'geocoded_at'=> (string)($geo['geocoded_at'] ?? ''),

                // quick flags
                'has_geo'     => ($lat !== '' && $lng !== ''),
                'has_photo'   => ((int)($p['avatar_id'] ?? 0) > 0),
                'has_bio'     => (trim((string)($p['bio'] ?? '')) !== ''),
                'has_address' => (trim((string)($address['formatted'] ?? '')) !== ''),
            );
        }

        return $rows;
    }
}
