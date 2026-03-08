<?php
/**
 * Plugin Name: Genesis DC Link My Page
 * Description: Displays an admin-defined Discord invite link on the URM profile page.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Admin settings
// ---------------------------------------------------------------------------

add_action( 'admin_init', 'genesis_dc_link_register_settings' );

function genesis_dc_link_register_settings() {
    register_setting( 'general', 'genesis_discord_invite_url', [
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ] );

    add_settings_field(
        'genesis_discord_invite_url',
        __( 'Discord Invite Link', 'genesis-wp-members' ),
        'genesis_dc_link_field_html',
        'general'
    );
}

function genesis_dc_link_field_html() {
    $value = get_option( 'genesis_discord_invite_url', '' );
    echo '<input type="url" name="genesis_discord_invite_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://discord.gg/..." />';
}

// ---------------------------------------------------------------------------
// Front-end injection on URM profile page
// ---------------------------------------------------------------------------

add_action( 'wp_footer', 'genesis_dc_link_inject_on_profile' );

function genesis_dc_link_inject_on_profile() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $invite_url = get_option( 'genesis_discord_invite_url', '' );
    if ( ! $invite_url ) {
        return;
    }
    ?>
    <script>
    (function() {
        function injectDiscordLink() {
            var rows = document.querySelectorAll('.ur-profile-details-content .ur-form-row, .user-registration-MyAccount-content .ur-form-row, .ur-my-account-profile .ur-form-row');
            if ( ! rows.length ) return false;

            if ( document.querySelector('.genesis-discord-link') ) return true;

            var lastRow = rows[ rows.length - 1 ];
            var container = lastRow.parentNode;

            var existingCol = lastRow.querySelector('[class*="ur-form-grid"]');
            var colClass = existingCol ? existingCol.className : 'ur-form-grid';

            var row = document.createElement('div');
            row.className = lastRow.className + ' genesis-discord-link';

            row.innerHTML = '<div class="' + colClass + '">'
                + '<div class="ur-field-item">'
                + '<label><?php echo esc_js( __( 'Discord', 'genesis-wp-members' ) ); ?></label>'
                + '<div style="display:flex;align-items:center;gap:8px;">'
                + '<input type="text" value="<?php echo esc_js( $invite_url ); ?>" readonly disabled'
                + ' style="background:#f5f5f5;cursor:default;flex:1;">'
                + '<a href="<?php echo esc_js( $invite_url ); ?>" target="_blank" rel="noopener noreferrer"'
                + ' style="white-space:nowrap;"><?php echo esc_js( __( 'Liity Discordiin', 'genesis-wp-members' ) ); ?></a>'
                + '</div>'
                + '</div>'
                + '</div>';

            container.appendChild( row );
            return true;
        }

        if ( ! injectDiscordLink() ) {
            var tries = 0;
            var timer = setInterval( function() {
                if ( injectDiscordLink() || ++tries > 20 ) clearInterval( timer );
            }, 200 );
        }
    })();
    </script>
    <?php
}
