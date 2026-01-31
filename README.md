# WordPress User Security Enhancements

Enforces a strong password policy for WordPress users and forces a password change after plugin activation (or when manually triggered by an admin).

## Features
- Minimum password length: 22 characters
- Requires at least one uppercase letter, one number, and one special character
- Validates passwords on registration, profile update, and password reset
- Forces logged-in users to change their password after activation
- Admin page to trigger a new forced password change for all users

## Installation
1. Copy this plugin into `wp-content/plugins/bezpecnostni-upravy-usera`.
2. Activate it in the WordPress admin.

## Usage
- After activation, any logged-in user whose password predates activation will be redirected to their profile and prompted to change it.
- The profile screen shows a sticky notice and a modal explaining the password requirements.
- An admin can re-trigger a forced password change in **Users → Force password change**.

## Password policy
- Minimum length: 22 characters
- At least one uppercase letter
- At least one number
- At least one special character
- Password confirmation must match

## Notes
- The plugin stores timestamps in user meta to track when a password was last changed.
- Strings are translation-ready (text domain: `bezpecnostni-upravy-usera`).

## License
GPL-2.0-or-later
