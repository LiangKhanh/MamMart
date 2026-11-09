<?php
/**
 * WooCommerceKlaviyo Consent Functions
 *
 * Shared helpers for building mobile (SMS / WhatsApp) consent records and resolving
 * which mobile channels are enabled in `klaviyo_settings`. Used by both the legacy
 * checkout (`wck-cart-functions.php`) and the Block checkout (`includes/blocks/StoreApi.php`).
 *
 * @package   WooCommerceKlaviyo/Functions
 * @since     3.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Resolve which mobile consent channels are enabled in plugin settings.
 *
 * Both SMS and WhatsApp share the legacy `klaviyo_sms_*` settings (list ID, consent
 * text, disclosure text) — there is intentionally no separate `klaviyo_mobile_list_id`
 * or `klaviyo_whatsapp_list_id` key. The WhatsApp toggle (`klaviyo_whatsapp_subscribe_checkbox`)
 * is pushed by the `app` integration and may be absent for stores that have not yet
 * opted into WhatsApp; in that case only SMS is considered.
 *
 * A channel is only considered "enabled" if its toggle is true AND the shared
 * `klaviyo_sms_list_id` is populated (without a list there is nowhere to send opt-ins).
 *
 * Tolerates non-array input (e.g. `false` from `get_option()` on unconfigured installs)
 * by coercing to an empty array — accessing string offsets on a bool/null would emit
 * "Trying to access array offset on value of type X" warnings on PHP 8 (which `empty()`
 * does not suppress, unlike `isset()` / `??`).
 *
 * @param mixed $settings The `klaviyo_settings` option array (or whatever `get_option()` returned).
 * @return array{sms:bool,whatsapp:bool}
 */
function kl_mobile_channels_enabled( $settings ) {
	$settings = is_array( $settings ) ? $settings : array();
	$has_list = ! empty( $settings['klaviyo_sms_list_id'] );

	return array(
		'sms'      => $has_list && ! empty( $settings['klaviyo_sms_subscribe_checkbox'] ),
		'whatsapp' => $has_list && ! empty( $settings['klaviyo_whatsapp_subscribe_checkbox'] ),
	);
}

/**
 * True when at least one mobile channel (SMS or WhatsApp) is enabled in settings.
 *
 * Safe to call with `false` / `null` / non-array input — `kl_mobile_channels_enabled()`
 * coerces non-array input to an empty array.
 *
 * @param mixed $settings The `klaviyo_settings` option array (or whatever `get_option()` returned).
 * @return bool
 */
function kl_any_mobile_channel_enabled( $settings ) {
	$channels = kl_mobile_channels_enabled( $settings );
	return $channels['sms'] || $channels['whatsapp'];
}

/**
 * Build one consent record per enabled mobile channel for the consent webhook payload.
 *
 * The returned records are intended to be merged into `$body['data']` of the POST to
 * `/api/webhook/integration/woocommerce`. Each record is shaped per the webhook contract:
 *
 *   {
 *     "customer":     { "email": ..., "phone": ..., "country": ... },
 *     "consent":      true,
 *     "updated_at":   "<ISO-8601>",
 *     "consent_type": "sms" | "whatsapp",
 *     "group_id":     "<shared mobile list id>"
 *   }
 *
 * Both channels reuse the same `klaviyo_sms_list_id` as `group_id` — this is intentional
 * (per IES-212) so that WhatsApp and SMS subscribers land on the same Klaviyo list.
 *
 * @param array  $customer Associative array with `email`, `phone`, `country` keys.
 * @param array  $channels Output of `kl_mobile_channels_enabled()` indicating which channels to emit.
 * @param string $list_id  Shared mobile list id used as `group_id` for every record.
 * @return array<int,array<string,mixed>> Zero, one, or two records (one per enabled channel).
 */
function kl_build_mobile_consent_records( $customer, $channels, $list_id ) {
	$records   = array();
	$timestamp = gmdate( DATE_ATOM, date_timestamp_get( date_create() ) );

	$consent_types_in_emit_order = array( 'sms', 'whatsapp' );
	foreach ( $consent_types_in_emit_order as $consent_type ) {
		if ( empty( $channels[ $consent_type ] ) ) {
			continue;
		}

		$records[] = array(
			'customer'     => array(
				'email'   => $customer['email'] ?? null,
				'country' => $customer['country'] ?? null,
				'phone'   => $customer['phone'] ?? null,
			),
			'consent'      => true,
			'updated_at'   => $timestamp,
			'consent_type' => $consent_type,
			'group_id'     => $list_id,
		);
	}

	return $records;
}
