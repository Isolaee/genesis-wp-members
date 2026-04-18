# Genesis WP Members

## Overview
A WordPress plugin that automatically assigns unique membership numbers to users at registration and backfills numbers to any existing accounts that are missing one.

## Problem It Solves
- Membership sites need a human-readable identifier for each member that is distinct from the WordPress user ID
- Manually assigning membership numbers is error-prone and does not scale
- Target users: WordPress developers building membership or association sites

## Use Cases
1. A new user registers — the plugin instantly assigns a unique 6-character alphanumeric number (e.g. `A3KF9Z`) stored in user meta
2. An existing site activates the plugin — all users without a membership number are backfilled automatically on activation with no manual work
3. A membership admin looks up a member by their number without needing to know the WordPress user ID

## Key Features
- **Auto-assign on registration** — hooks into `user_register`, zero configuration required
- **Activation backfill** — existing users get numbers when the plugin is activated
- **Collision-safe generation** — uniqueness is verified against existing meta values before assigning

## Tech Stack
- PHP 7.0+
- WordPress 5.0+
- No external dependencies

## Getting Started

1. Copy the `genesis-wp-members` folder to `wp-content/plugins/`
2. Activate **Genesis WP Members** in the WordPress admin under **Plugins**

Membership numbers are assigned immediately to all existing users on activation. New registrations are handled automatically from that point forward. Numbers are stored under the `membership_number` user meta key and can be retrieved with:

```php
get_user_meta( $user_id, 'membership_number', true );
```
