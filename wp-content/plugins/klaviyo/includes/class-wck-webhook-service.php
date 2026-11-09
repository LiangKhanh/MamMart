<?php
/**
 * WooCommerceKlaviyo Webhook Service
 *
 * Handles outgoing requests to Klaviyo's webhook endpoint.
 *
 * @package     WooCommerceKlaviyo/Webhook
 * @since       2.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WCK_Webhook_Service
 *
 * Handle sending data to Klaviyo's webhook endpoint synchronously.
 */
class WCK_Webhook_Service {

	const WEBHOOK_URL = 'https://a.klaviyo.com/api/webhook/integration/woocommerce?c=';

	const TOPIC_RESOURCE_CUSTOM = 'custom';
	const TOPIC_EVENT_OPTIONS   = 'options';
	const TOPIC_EVENT_REMOVE    = 'remove';
	const TOPIC_EVENT_VERSION   = 'version';
	const SIGNATURE_HEADER      = 'X-WC-Webhook-Signature';
	const MAX_WEBHOOKS_TO_SCAN  = 10000;

	/**
	 * Handle building args, sending request to Klaviyo's webhook url and lightweight error handling.
	 *
	 * @param string $topic_event Webhook topic event in the pattern 'resource/event'.
	 * @param array  $data Payload of outgoing request.
	 * @return array|void
	 */
	private function send_webhook( $topic_event, $data ) {
		$options = get_option( 'klaviyo_settings' );
		if ( ! isset( $options['klaviyo_public_api_key'] ) ) {
			// TODO: It'd be nice to eventually log this failure or notify in the admin.
			return;
		}
		$url = self::WEBHOOK_URL . $options['klaviyo_public_api_key'];

		$body_json = wp_json_encode( $data );
		$headers   = array(
			'X-WC-Webhook-Topic' => self::TOPIC_RESOURCE_CUSTOM . '/' . $topic_event,
			'Content-Type'       => 'application/json',
		);
		$signature = $this->sign_payload( $body_json );
		if ( null !== $signature ) {
			$headers[ self::SIGNATURE_HEADER ] = $signature;
		}

		// Don't set 'blocking' = false, it short circuits response parsing and returns an empty Requests_Response
		// object. For more information see Requests::parse_response() in wordpress/wp-includes/class-requests.php.
		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body_json,
			)
		);

		// Klaviyo's webhook endpoints almost always return 200 with a body of "1"/"0" corresponding to success/failure.
		// It's possible to get a 503 response in the case of a larger issue unrelated to content, formatting, etc. or a
		// timeout if the request takes longer than 5 seconds.
		if ( is_wp_error( $response ) || '1' !== $response['body'] || 200 !== $response['response']['code'] ) {
			// TODO: It'd be nice to eventually log this failure.
			return;
		}

		return $response;
	}

	/**
	 * Send webhook with topic 'custom/options'. Data contains all options under 'klaviyo_settings',
	 * the plugin version and if it's the most recent plugin version.
	 *
	 * Set email/sms list ID values to null if no ID set.
	 *
	 * @param boolean $is_updating Whether the plugin is being updated.
	 */
	public function send_options_webhook( $is_updating = false ) {
		$data = array_merge( WCK_API::build_version_payload( $is_updating ), WCK()->options->get_all_options() );

		if ( ! isset( $data['klaviyo_sms_list_id'] ) || '' === $data['klaviyo_sms_list_id'] ) {
			$data['klaviyo_sms_list_id'] = null;
		}
		if ( ! isset( $data['klaviyo_newsletter_list_id'] ) || '' === $data['klaviyo_newsletter_list_id'] ) {
			$data['klaviyo_newsletter_list_id'] = null;
		}

		return $this->send_webhook( self::TOPIC_EVENT_OPTIONS, $data );
	}

	/**
	 * Signs a payload the same way WooCommerce signs its own webhooks.
	 * Public so wck-cart-functions.php can use it too.
	 *
	 * @param string $body_json The JSON request body.
	 * @return string|null Null if no secret is available yet.
	 */
	public function sign_payload( $body_json ) {
		$secret = $this->get_signing_secret();
		if ( ! $secret ) {
			return null;
		}

		// WooCommerce decodes HTML entities in the secret before signing. Do the same, or signatures do not match.
		return base64_encode( hash_hmac( 'sha256', $body_json, wp_specialchars_decode( $secret, ENT_QUOTES ), true ) );
	}

	/**
	 * Returns null if the lookup throws, so checkout is not affected.
	 *
	 * @return string|null
	 */
	protected function get_signing_secret() {
		try {
			return $this->scan_registered_webhooks_for_secret();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Loops over core webhooks to find the secret there to use for the custom webhooks.
	 *
	 * @return string|null
	 */
	protected function scan_registered_webhooks_for_secret() {
		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return null;
		}

		$webhook_data_store = WC_Data_Store::load( 'webhook' );
		return $this->find_secret_in_webhook_ids( $webhook_data_store->get_webhooks_ids() );
	}

	/**
	 * Stops after MAX_WEBHOOKS_TO_SCAN webhooks, so a store with a huge number
	 * of webhooks cannot turn this into a slow scan on every checkout.
	 *
	 * @param int[] $webhook_ids
	 * @return string|null
	 */
	protected function find_secret_in_webhook_ids( $webhook_ids ) {
		foreach ( array_slice( $webhook_ids, 0, self::MAX_WEBHOOKS_TO_SCAN ) as $webhook_id ) {
			$webhook = wc_get_webhook( $webhook_id );
			if ( $webhook && $this->is_klaviyo_webhook_url( $webhook->get_delivery_url() ) ) {
				return $webhook->get_secret();
			}
		}

		return null;
	}

	/**
	 * Checks the host and path of a webhook's delivery_url.
	 *
	 * @param string $delivery_url
	 * @return bool
	 */
	protected function is_klaviyo_webhook_url( $delivery_url ) {
		$host = wp_parse_url( $delivery_url, PHP_URL_HOST );
		$path = wp_parse_url( $delivery_url, PHP_URL_PATH );

		if ( ! $host || ! $path ) {
			return false;
		}

		$is_klaviyo_host             = 'klaviyo.com' === $host || '.klaviyo.com' === substr( $host, -12 );
		$is_woocommerce_webhook_path = false !== strpos( $path, '/api/webhook/integration/woocommerce' );

		return $is_klaviyo_host && $is_woocommerce_webhook_path;
	}
}
