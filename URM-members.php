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
 * Temporary debug: dump all URM hooks firing on this page visibly + membership number.
 * Remove after confirmed working.
 */
add_action( 'wp_footer', function() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    $n = get_user_meta( get_current_user_id(), 'membership_number', true );
    echo '<div style="position:fixed;bottom:0;left:0;right:0;background:#000;color:#0f0;font-family:monospace;font-size:12px;padding:8px;z-index:99999;max-height:200px;overflow:auto;">';
    echo '<strong>DEBUG</strong> membership_number: ' . esc_html( $n ) . '<br>';
    global $wp_filter;
    $urm_hooks = array_filter( array_keys( $wp_filter ), function( $h ) {
        return strpos( $h, 'um_' ) === 0 || strpos( $h, 'user_registration_' ) === 0;
    } );
    echo '<strong>URM hooks fired:</strong> ' . esc_html( implode( ', ', $urm_hooks ) );
    echo '</div>';
} );

/**
 * Display the membership number on the WPeverest User Registration profile page.
 * Injected via wp_footer JS because the profile fields hook output is buffered/discarded.
 */
add_action( 'wp_footer', 'genesis_display_membership_number_on_profile' );

function genesis_display_membership_number_on_profile() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    $number  = get_user_meta( $user_id, 'membership_number', true );
    if ( ! $number ) {
        return;
    }
    ?>
    <script>
    (function() {
        function injectMembershipNumber() {
            // Target the profile fields grid inside the URM My Account page.
            var grid = document.querySelector('.ur-profile-details-content .ur-form-grid, .user-registration-MyAccount-content .ur-form-grid, .ur-my-account-profile .ur-form-grid');
            if ( ! grid ) return false;

            // Avoid double-injection on re-runs.
            if ( document.querySelector('.genesis-membership-number') ) return true;

            var html = '<div class="ur-form-row genesis-membership-number" style="margin-top:16px;">'
                + '<div class="ur-label"><label><?php echo esc_js( __( 'Jäsen numero', 'genesis-wp-members' ) ); ?></label></div>'
                + '<div class="ur-field"><p class="ur-label-value"><?php echo esc_js( $number ); ?></p></div>'
                + '</div>';

            grid.insertAdjacentHTML( 'beforeend', html );
            return true;
        }

        // Try immediately, then retry until the DOM is ready.
        if ( ! injectMembershipNumber() ) {
            var tries = 0;
            var timer = setInterval( function() {
                if ( injectMembershipNumber() || ++tries > 20 ) clearInterval( timer );
            }, 200 );
        }
    })();
    </script>
    <?php
}
