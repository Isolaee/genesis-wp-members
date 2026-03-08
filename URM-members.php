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
 * Display the membership number on the WPeverest User Registration profile page.
 * Injected via wp_footer JS to match the existing two-column field layout.
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
            // Find the last ur-form-row inside the profile content to insert after it.
            var rows = document.querySelectorAll('.ur-profile-details-content .ur-form-row, .user-registration-MyAccount-content .ur-form-row, .ur-my-account-profile .ur-form-row');
            if ( ! rows.length ) return false;

            // Avoid double-injection.
            if ( document.querySelector('.genesis-membership-number') ) return true;

            // Clone structure from an existing field row to match styling exactly.
            var lastRow = rows[ rows.length - 1 ];
            var container = lastRow.parentNode;

            // Build a row that matches the URM field structure: label above, value in input-like box.
            var row = document.createElement('div');
            row.className = lastRow.className + ' genesis-membership-number';

            // Find an existing field column to copy its class (ur-form-grid col-half etc.)
            var existingCol = lastRow.querySelector('[class*="ur-form-grid"]');
            var colClass = existingCol ? existingCol.className : 'ur-form-grid';

            row.innerHTML = '<div class="' + colClass + '">'
                + '<div class="ur-field-item">'
                + '<label><?php echo esc_js( __( 'Jäsen numero', 'genesis-wp-members' ) ); ?></label>'
                + '<input type="text" value="<?php echo esc_js( $number ); ?>" readonly disabled'
                + ' style="background:#f5f5f5;cursor:default;">'
                + '</div>'
                + '</div>';

            container.appendChild( row );
            return true;
        }

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

// ---------------------------------------------------------------------------
// Admin page: membership number list + CSV export
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'genesis_membership_admin_menu' );

function genesis_membership_admin_menu() {
    add_users_page(
        __( 'Membership Numbers', 'genesis-wp-members' ),
        __( 'Membership Numbers', 'genesis-wp-members' ),
        'manage_options',
        'genesis-membership-numbers',
        'genesis_membership_admin_page'
    );
}

function genesis_membership_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'genesis-wp-members' ) );
    }

    // Handle CSV export before any output.
    if ( isset( $_GET['genesis_export_csv'] ) && check_admin_referer( 'genesis_export_csv' ) ) {
        genesis_membership_export_csv();
        exit;
    }

    $users = get_users( [
        'meta_key'     => 'membership_number',
        'meta_compare' => 'EXISTS',
        'orderby'      => 'display_name',
        'order'        => 'ASC',
    ] );

    $export_url = wp_nonce_url(
        admin_url( 'users.php?page=genesis-membership-numbers&genesis_export_csv=1' ),
        'genesis_export_csv'
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Membership Numbers', 'genesis-wp-members' ); ?></h1>
        <p>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
                <?php esc_html_e( 'Export CSV', 'genesis-wp-members' ); ?>
            </a>
        </p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Username', 'genesis-wp-members' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'genesis-wp-members' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'genesis-wp-members' ); ?></th>
                    <th><?php esc_html_e( 'Membership Number', 'genesis-wp-members' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $users as $user ) :
                $number = get_user_meta( $user->ID, 'membership_number', true );
            ?>
                <tr>
                    <td><?php echo esc_html( $user->user_login ); ?></td>
                    <td><?php echo esc_html( $user->display_name ); ?></td>
                    <td><?php echo esc_html( $user->user_email ); ?></td>
                    <td><code><?php echo esc_html( $number ); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><?php printf( esc_html__( 'Total: %d members', 'genesis-wp-members' ), count( $users ) ); ?></p>
    </div>
    <?php
}

function genesis_membership_export_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'genesis-wp-members' ) );
    }

    $users = get_users( [
        'meta_key'     => 'membership_number',
        'meta_compare' => 'EXISTS',
        'orderby'      => 'display_name',
        'order'        => 'ASC',
    ] );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="membership-numbers-' . gmdate( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM for Excel.
    fputcsv( $out, [ 'Username', 'Name', 'Email', 'Membership Number' ] );

    foreach ( $users as $user ) {
        fputcsv( $out, [
            $user->user_login,
            $user->display_name,
            $user->user_email,
            get_user_meta( $user->ID, 'membership_number', true ),
        ] );
    }

    fclose( $out );
}
