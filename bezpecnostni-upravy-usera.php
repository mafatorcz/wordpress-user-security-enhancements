<?php
/**
 * Plugin Name: Bezpecnostni upravy usera
 * Description: Vynucuje minimalni delku hesla 22 znaku a zmenu hesla po aktivaci pluginu.
 * Version: 1.2.0
 * Author: Martin Fucik
 * License: GPL-2.0-or-later
 * Text Domain: bezpecnostni-upravy-usera
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BU_MIN_PASSWORD_LENGTH = 22;
const BU_OPTION_ACTIVATED_AT = 'bu_force_change_activated_at';
const BU_META_PASSWORD_CHANGED_AT = 'bu_password_changed_at';

function bu_load_textdomain() {
	load_plugin_textdomain(
		'bezpecnostni-upravy-usera',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'bu_load_textdomain' );

function bu_activate_plugin() {
	update_option( BU_OPTION_ACTIVATED_AT, time(), false );
}
register_activation_hook( __FILE__, 'bu_activate_plugin' );

function bu_password_length_error( WP_Error $errors, $password ) {
	if ( empty( $password ) ) {
		return;
	}

	if ( strlen( $password ) < BU_MIN_PASSWORD_LENGTH ) {
		$errors->add(
			'bu_password_too_short',
			sprintf(
				__( 'Password must be at least %d characters.', 'bezpecnostni-upravy-usera' ),
				BU_MIN_PASSWORD_LENGTH
			)
		);
	}

	if ( ! preg_match( '/\p{Lu}/u', $password ) ) {
		$errors->add(
			'bu_password_no_uppercase',
			__( 'Password must include at least one uppercase letter.', 'bezpecnostni-upravy-usera' )
		);
	}

	if ( ! preg_match( '/[0-9]/', $password ) ) {
		$errors->add(
			'bu_password_no_number',
			__( 'Password must include at least one number.', 'bezpecnostni-upravy-usera' )
		);
	}

	if ( ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
		$errors->add(
			'bu_password_no_special',
			__( 'Password must include at least one special character.', 'bezpecnostni-upravy-usera' )
		);
	}
}

function bu_validate_password_reset( $errors, $user ) {
	if ( isset( $_POST['pass1'] ) ) {
		bu_password_length_error( $errors, wp_unslash( $_POST['pass1'] ) );
	}
}
add_action( 'validate_password_reset', 'bu_validate_password_reset', 10, 2 );

function bu_profile_password_validation( $errors, $update, $user ) {
	if ( empty( $_POST['pass1'] ) ) {
		return;
	}

	if ( ! isset( $_POST['pass2'] ) || $_POST['pass1'] !== $_POST['pass2'] ) {
		return;
	}

	bu_password_length_error( $errors, wp_unslash( $_POST['pass1'] ) );
}
add_action( 'user_profile_update_errors', 'bu_profile_password_validation', 10, 3 );

function bu_registration_password_validation( $errors, $sanitized_user_login, $user_email ) {
	if ( isset( $_POST['pass1'] ) ) {
		bu_password_length_error( $errors, wp_unslash( $_POST['pass1'] ) );
	}

	return $errors;
}
add_filter( 'registration_errors', 'bu_registration_password_validation', 10, 3 );

function bu_custom_password_hint( $hint ) {
	return __( 'Hint: Password must be at least 22 characters long and include an uppercase letter, a number, and a special character.', 'bezpecnostni-upravy-usera' );
}
add_filter( 'password_hint', 'bu_custom_password_hint' );

function bu_mark_password_changed( $user_id ) {
	update_user_meta( $user_id, BU_META_PASSWORD_CHANGED_AT, time() );
}

function bu_mark_password_changed_after_reset( $user, $new_pass ) {
	bu_mark_password_changed( $user->ID );
}
add_action( 'after_password_reset', 'bu_mark_password_changed_after_reset', 10, 2 );

function bu_mark_password_changed_after_profile_update( $user_id ) {
	if ( empty( $_POST['pass1'] ) ) {
		return;
	}

	bu_mark_password_changed( $user_id );
}
add_action( 'profile_update', 'bu_mark_password_changed_after_profile_update' );

function bu_mark_password_changed_after_register( $user_id ) {
	bu_mark_password_changed( $user_id );
}
add_action( 'user_register', 'bu_mark_password_changed_after_register' );

function bu_is_password_change_required( $user_id ) {
	$activated_at = (int) get_option( BU_OPTION_ACTIVATED_AT, 0 );
	if ( $activated_at <= 0 ) {
		return false;
	}

	$changed_at = (int) get_user_meta( $user_id, BU_META_PASSWORD_CHANGED_AT, true );
	if ( $changed_at <= 0 ) {
		return true;
	}

	return $changed_at < $activated_at;
}

function bu_is_force_change_request_allowed() {
	if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	$allowed = array(
		'profile.php',
		'wp-login.php',
		'admin-ajax.php',
	);

	global $pagenow;
	return in_array( $pagenow, $allowed, true );
}

function bu_maybe_force_password_change() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! bu_is_password_change_required( $user_id ) ) {
		return;
	}

	if ( bu_is_force_change_request_allowed() ) {
		return;
	}

	wp_safe_redirect( admin_url( 'profile.php?bu_force_password_change=1' ) );
	exit;
}
add_action( 'admin_init', 'bu_maybe_force_password_change' );
add_action( 'template_redirect', 'bu_maybe_force_password_change' );

function bu_should_show_force_password_prompt() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( ! bu_is_password_change_required( get_current_user_id() ) ) {
		return false;
	}

	if ( empty( $_GET['bu_force_password_change'] ) ) {
		return false;
	}

	global $pagenow;
	return $pagenow === 'profile.php';
}

function bu_force_password_notice_styles() {
	if ( ! bu_should_show_force_password_prompt() ) {
		return;
	}

	echo '<style>
		.bu-force-password-notice {
			border-left-width: 6px;
			box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
		}
		.bu-force-password-notice p {
			font-size: 15px;
		}
		.bu-force-password-notice p strong {
			font-size: 16px;
		}
		.bu-force-password-sticky {
			position: sticky;
			top: 32px;
			z-index: 9999;
		}
		.bu-force-password-sticky .bu-force-password-title {
			font-size: 16px;
			margin: 0 0 6px 0;
		}
		.bu-force-password-sticky .bu-force-password-body {
			font-weight: 600;
			margin: 0 0 8px 0;
		}
		.bu-force-password-sticky ul {
			margin: 6px 0 0 20px;
			color: #4b4f56;
		}
		.bu-force-password-modal__title {
			margin: 0 0 6px 0;
		}
		.bu-force-password-modal__body {
			font-weight: 600;
			margin: 0 0 12px 0;
		}
		.bu-force-password-modal__list {
			margin: 0 0 12px 20px;
			color: #4b4f56;
		}
		.bu-password-focus .user-pass1-wrap,
		.bu-password-focus .user-pass2-wrap {
			border: 1px solid #d63638;
			background: #fff8f8;
			padding: 12px;
			border-radius: 4px;
		}
		.bu-password-focus .user-pass1-wrap {
			margin-bottom: 10px;
		}
		.bu-password-hint {
			margin-top: 6px;
			color: #50575e;
		}
		.bu-password-hint em {
			font-style: normal;
			font-weight: 600;
		}
		.bu-password-counter {
			margin-top: 6px;
			font-weight: 600;
		}
		.bu-password-counter.is-invalid {
			color: #d63638;
		}
		.bu-password-counter.is-valid {
			color: #008a20;
		}
		.bu-force-password-modal {
			position: fixed;
			inset: 0;
			z-index: 100000;
			display: none;
			align-items: center;
			justify-content: center;
			background: rgba(0, 0, 0, 0.45);
		}
		.bu-force-password-modal.is-visible {
			display: flex;
		}
		.bu-force-password-modal__content {
			background: #fff;
			border-radius: 6px;
			box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
			max-width: 520px;
			width: calc(100% - 40px);
			padding: 20px;
		}
		.bu-force-password-modal__actions {
			display: flex;
			gap: 8px;
			margin-top: 16px;
		}
		.bu-force-password-modal__actions .button {
			min-width: 150px;
		}
	</style>';
}
add_action( 'admin_head', 'bu_force_password_notice_styles' );

function bu_force_password_sticky_notice() {
	if ( ! bu_should_show_force_password_prompt() ) {
		return;
	}

	echo '<div class="notice notice-error bu-force-password-sticky">';
	echo '<p class="bu-force-password-title"><strong>' . esc_html__( 'Password change required', 'bezpecnostni-upravy-usera' ) . '</strong></p>';
	echo '<p class="bu-force-password-body">' . esc_html__( 'For security reasons, please change your password. Minimum length is 22 characters.', 'bezpecnostni-upravy-usera' ) . '</p>';
	echo '<ul>';
	echo '<li>' . esc_html__( 'Minimum length: 22 characters', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'At least one uppercase letter', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'At least one number', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'At least one special character', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'Passwords must match', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'Save the password using the Update Profile button at the bottom.', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '</ul>';
	echo '</div>';
}
add_action( 'admin_notices', 'bu_force_password_sticky_notice', 5 );

function bu_force_password_modal() {
	if ( ! bu_should_show_force_password_prompt() ) {
		return;
	}

	echo '<div class="bu-force-password-modal" id="bu-force-password-modal" role="dialog" aria-modal="true" aria-labelledby="bu-force-password-title">';
	echo '<div class="bu-force-password-modal__content">';
	echo '<h2 id="bu-force-password-title" class="bu-force-password-modal__title">' . esc_html__( 'Password change required', 'bezpecnostni-upravy-usera' ) . '</h2>';
	echo '<p class="bu-force-password-modal__body">' . esc_html__( 'For security reasons, please change your password. Minimum length is 22 characters.', 'bezpecnostni-upravy-usera' ) . '</p>';
	echo '<ul class="bu-force-password-modal__list">';
	echo '<li>' . esc_html__( 'Minimum length: 22 characters', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'At least one uppercase letter', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'At least one number', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'At least one special character', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'Passwords must match', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '<li>' . esc_html__( 'Save the password using the Update Profile button at the bottom.', 'bezpecnostni-upravy-usera' ) . '</li>';
	echo '</ul>';
	echo '<div class="bu-force-password-modal__actions">';
	echo '<button type="button" class="button button-primary" id="bu-force-password-scroll">' . esc_html__( 'Go to password fields', 'bezpecnostni-upravy-usera' ) . '</button>';
	echo '<button type="button" class="button" id="bu-force-password-close">' . esc_html__( 'Continue', 'bezpecnostni-upravy-usera' ) . '</button>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
}
add_action( 'admin_footer', 'bu_force_password_modal' );

function bu_force_password_modal_script() {
	if ( ! bu_should_show_force_password_prompt() ) {
		return;
	}

	echo '<script>
		(function() {
			var i18n = ' . wp_json_encode(
		array(
			'passphrase_hint_full' => __( 'Recommendation: use a long passphrase. You can verify it with the Show password button.', 'bezpecnostni-upravy-usera' ),
			'confirm_hint'         => __( 'Repeat the same password to confirm.', 'bezpecnostni-upravy-usera' ),
			'counter_template'     => __( 'Password length: %1$d / %2$d (uppercase letter, number, special character)', 'bezpecnostni-upravy-usera' ),
		)
	) . ';
			var modal = document.getElementById("bu-force-password-modal");
			if (!modal) {
				return;
			}
			var body = document.body;
			var scrollButton = document.getElementById("bu-force-password-scroll");
			var closeButton = document.getElementById("bu-force-password-close");
			var pass1 = document.getElementById("pass1");
			var pass2 = document.getElementById("pass2");
			var generateButton = document.querySelector(".wp-generate-pw");
			var showButton = document.querySelector(".wp-hide-pw");
			var minLength = 22;
			var submitButtons = document.querySelectorAll("input[type=\\"submit\\"], button[type=\\"submit\\"]");
			modal.classList.add("is-visible");
			body.style.overflow = "hidden";
			body.classList.add("bu-password-focus");

			if (generateButton && pass1 && pass1.value === "") {
				generateButton.click();
				window.setTimeout(updateValidationState, 0);
			}

			if (pass1 && !document.querySelector(".bu-password-hint")) {
				var hint = document.createElement("p");
				hint.className = "bu-password-hint description";
				hint.textContent = i18n.passphrase_hint_full;
				pass1.parentNode.appendChild(hint);
			}

			if (pass1 && !document.querySelector(".bu-password-counter")) {
				var counter = document.createElement("p");
				counter.className = "bu-password-counter";
				counter.setAttribute("data-min-length", String(minLength));
				pass1.parentNode.appendChild(counter);
			}

			if (pass2 && !document.querySelector(".bu-password-hint--confirm")) {
				var confirmHint = document.createElement("p");
				confirmHint.className = "bu-password-hint bu-password-hint--confirm description";
				confirmHint.textContent = i18n.confirm_hint;
				pass2.parentNode.appendChild(confirmHint);
			}

			function updateValidationState() {
				var counterEl = document.querySelector(".bu-password-counter");
				var pass1Value = pass1 ? pass1.value : "";
				var pass2Value = pass2 ? pass2.value : "";
				var lengthOk = pass1Value.length >= minLength;
				var uppercaseOk = /[A-Z]/.test(pass1Value);
				var numberOk = /[0-9]/.test(pass1Value);
				var specialOk = /[^a-zA-Z0-9]/.test(pass1Value);
				var matchOk = pass1Value.length > 0 && pass1Value === pass2Value;
				var isValid = lengthOk && uppercaseOk && numberOk && specialOk && matchOk;

				if (counterEl) {
					counterEl.classList.remove("is-valid", "is-invalid");
					counterEl.classList.add(isValid ? "is-valid" : "is-invalid");
					counterEl.textContent = i18n.counter_template
						.replace("%1$d", pass1Value.length)
						.replace("%2$d", minLength);
				}

				if (submitButtons && submitButtons.length) {
					submitButtons.forEach(function(button) {
						button.disabled = !isValid;
					});
				}
			}

			if (pass1) {
				pass1.addEventListener("input", updateValidationState);
			}
			if (pass2) {
				pass2.addEventListener("input", updateValidationState);
			}
			if (generateButton) {
				generateButton.addEventListener("click", function() {
					window.setTimeout(updateValidationState, 0);
				});
			}
			updateValidationState();

			function closeModal() {
				modal.classList.remove("is-visible");
				body.style.overflow = "";
			}

			if (scrollButton) {
				scrollButton.addEventListener("click", function() {
					closeModal();
					var pwdWrap = document.querySelector(".wp-pwd");
					if (generateButton && pwdWrap && !pwdWrap.classList.contains("is-open")) {
						generateButton.click();
					}

					window.setTimeout(function() {
						var target = document.getElementById("pass1") || document.querySelector("input[name=\\"pass1\\"]");
						if (target) {
							var focusTarget = target.closest(".user-pass1-wrap") || target;
							focusTarget.scrollIntoView({ behavior: "smooth", block: "center" });
							target.focus({ preventScroll: true });
						}
						if (showButton && showButton.getAttribute("aria-pressed") === "false") {
							showButton.click();
						}
					}, 120);
				});
			}

			if (closeButton) {
				closeButton.addEventListener("click", closeModal);
			}
		})();
	</script>';
}
add_action( 'admin_footer', 'bu_force_password_modal_script', 20 );

function bu_register_admin_page() {
	add_users_page(
		__( 'Force password change', 'bezpecnostni-upravy-usera' ),
		__( 'Force password change', 'bezpecnostni-upravy-usera' ),
		'manage_options',
		'bu-force-password',
		'bu_render_admin_page'
	);
}
add_action( 'admin_menu', 'bu_register_admin_page' );

function bu_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action_url = admin_url( 'admin-post.php' );
	$nonce      = wp_create_nonce( 'bu_force_password_again' );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Force password change', 'bezpecnostni-upravy-usera' ) . '</h1>';
	echo '<p>' . esc_html__( 'Click the button to force all users to change their password again.', 'bezpecnostni-upravy-usera' ) . '</p>';
	echo '<form method="post" action="' . esc_url( $action_url ) . '">';
	echo '<input type="hidden" name="action" value="bu_force_password_again">';
	echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">';
	echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Force password change again', 'bezpecnostni-upravy-usera' ) . '"></p>';
	echo '</form>';
	echo '</div>';
}

function bu_handle_force_password_again() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'bezpecnostni-upravy-usera' ) );
	}

	check_admin_referer( 'bu_force_password_again' );

	update_option( BU_OPTION_ACTIVATED_AT, time(), false );

	wp_safe_redirect( admin_url( 'users.php?page=bu-force-password&bu_forced=1' ) );
	exit;
}
add_action( 'admin_post_bu_force_password_again', 'bu_handle_force_password_again' );

