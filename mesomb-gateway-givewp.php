<?php
/**
 * Plugin Name: MeSomb for Give
 * Plugin URI: https://mesomb.hachther.com
 * Description: Plugin to integrate Mobile payment on GiveWP using Hachther MeSomb
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Author: Hachther LLC <contact@hachther.com>
 * Author URI: https://hachther.com
 * Text Domain: mesomb-for-give
 * Domain Path: /languages
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Register the gateways 
add_action('givewp_register_payment_gateway', static function ($paymentGatewayRegister) {
    include 'includes/mesomb-gateway-api.php';
//    include 'class-offsite-mesomb-gateway.php';
    include 'includes/mesomb-gateway.php';
    include 'admin/mesomb-gateway-admin.php';
//    $paymentGatewayRegister->registerGateway(MeSombGatewayOffsiteClass::class);
    $paymentGatewayRegister->registerGateway(MeSombGatewayOnsiteClass::class);
});

// Register the gateways subscription module for onsite example test gateway
 add_filter("givewp_gateway_onsite-example-test-gateway_subscription_module", static function () {
        include 'class-onsite-mesomb-gateway-subscription-module.php';

        return MeSombGatewayOnsiteSubscriptionModuleClass::class;
    }
);

/**
 * Register Admin Settings.
 *
 * @param array $settings List of admin settings.
 *
 * @since 1.1.0
 *
 * @return array
 */
function waf_paychangu_for_give_register_payment_gateway_setting_fields( $settings ) {

    switch ( give_get_current_setting_section() ) {

        case 'paychangu-settings':
            $settings = array(
                array(
                    'id'   => 'give_title_paychangu',
                    'desc' => 'Our Supported Currencies: <strong>' . esc_html(waf_paychangu_for_give_get_supported_currencies(true)) . '.</strong>',
                    'type' => 'title',
                ),
                array(
                    'id'   => 'paychangu-invoicePrefix',
                    'name' => 'Invoice Prefix',
                    'desc' => 'Please enter a prefix for your invoice numbers. If you use your Paychangu account for multiple stores ensure this prefix is unique as Paychangu will not allow orders with the same invoice number.',
                    'type' => 'text',
                ),
                array(
                    'id'   => 'paychangu-publicKey',
                    'name' => 'Public Key',
                    'desc' => 'Required: Enter your Public Key here. You can get your Public Key from <a href="https://in.paychangu.com/user/profile/api">here</a>',
                    'type' => 'text',
                ),
                array(
                    'id'   => 'paychangu-secretKey',
                    'name' => 'Secret Key',
                    'desc' => 'Required: Enter your Secret Key here. You can get your Secret Key from <a href="https://in.paychangu.com/user/profile/api">here</a>',
                    'type' => 'text',
                ),
                array(
                    'id'   => 'give_title_paychangu',
                    'type' => 'sectionend',
                )
            );

            break;

    } // End switch().

    return $settings;
}

add_filter( 'give_get_settings_gateways', 'waf_paychangu_for_give_register_payment_gateway_setting_fields' );