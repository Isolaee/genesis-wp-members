<?php
/**
 * Plugin Name: Genesis WP Members
 * Description: Custom refinements for user registration & membership.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * On plugin activation, assign membership numbers to all existing users that lack one.
 */
register_activation_hook( __FILE__, 'genesis_backfill_membership_numbers' );

function genesis_backfill_membership_numbers() {
    $users = get_users( [
        'meta_query' => [
            [
                'key'     => 'membership_number',
                'compare' => 'NOT EXISTS',
            ],
        ],
        'fields' => 'ids',
    ] );

    foreach ( $users as $user_id ) {
        $number = genesis_generate_unique_membership_number();
        update_user_meta( $user_id, 'membership_number', $number );
    }
}

/**
 * Generate a unique 6-character alphanumeric membership number when a new user is registered.
 * Format: XXXXXX (e.g. A3KF9Z)
 */
add_action( 'user_register', 'genesis_assign_membership_number' );

function genesis_assign_membership_number( $user_id ) {
    if ( get_user_meta( $user_id, 'membership_number', true ) ) {
        return;
    }

    $number = genesis_generate_unique_membership_number();
    update_user_meta( $user_id, 'membership_number', $number );
}

/**
 * Generate a random 6-character alphanumeric code, guaranteed unique across all users.
 */
function genesis_generate_unique_membership_number() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Omit ambiguous chars: 0/O, 1/I

    do {
        $code = '';
        for ( $i = 0; $i < 6; $i++ ) {
            $code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
        }
        $exists = get_users( [
            'meta_key'   => 'membership_number',
            'meta_value' => $code,
            'number'     => 1,
            'fields'     => 'ids',
        ] );
    } while ( ! empty( $exists ) );

    return $code;
}

/**
 * Temporary debug: output membership number in page source to confirm data & hook.
 * Remove after confirmed working.
 */
add_action( 'wp_footer', function() {
    if ( is_user_logged_in() ) {
        $n = get_user_meta( get_current_user_id(), 'membership_number', true );
        echo '<!-- membership_number: ' . esc_html( $n ) . ' -->';
    }
} );

/**
 * Display the membership number on the URM profile page.
 */
add_action( 'um_after_profile_fields', 'genesis_display_membership_number_on_profile' );

function genesis_display_membership_number_on_profile() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    $number = get_user_meta( $user_id, 'membership_number', true );
    if ( ! $number ) {
        return;
    }

    echo '<div class="um-field genesis-membership-number">';
    echo '<label>' . esc_html__( 'Membership Number', 'genesis-wp-members' ) . '</label>';
    echo '<span>' . esc_html( $number ) . '</span>';
    echo '</div>';
}
