<?php

// No direct access is allowed.
if (! defined('ABSPATH')) {
    exit;
}

//use HitPay\Client;
//use HitPay\Request\CreatePayment;
//use HitPay\Response\PaymentStatus;

/**
 * Proceed only, if class MeSomb_Gateway_Admin_Settings not exists.
 */
if (! class_exists('MeSomb_Gateway_Admin_Settings')) {

    /**
     * Class MeSomb_Gateway_Admin_Settings.
     */
    class MeSomb_Gateway_Admin_Settings
    {
        /**
         * MeSomb_Gateway_Admin_Settings constructor.
         */
        public function __construct()
        {
            add_action('give_admin_field_mesomb_title', array($this, 'render_mesomb_title'), 10, 2);
            add_action('give_admin_field_mesomb_label', array($this, 'render_mesomb_label'), 10, 2);
            add_filter('give_get_sections_gateways', array($this, 'register_sections'));
            add_action('give_get_settings_gateways', array($this, 'register_settings'));
            add_action('give_view_donation_details_totals_after', array($this, 'admin_order_totals'), 10, 2);
            add_action('give_view_donation_details_update_after', array($this, 'admin_refund_button'), 10, 2);
            add_action('give_updated_edited_donation', array($this, 'give_mesomb_process_refund'), 10, 2 );
        }

        public function getMode($userMode)
        {
            $mode = true;
            if ($userMode == 'no') {
                $mode = false;
            }
            return $mode;
        }

        public function admin_refund_button( $donationId )
        {
            if (give_get_payment_gateway($donationId) == 'mesomb' && give_is_payment_complete( $donationId )) {
                ?>
                <div id="major-publishing-actions">
                    <div id="publishing-action">
                        <input type="submit" name="mesomb_payment_refund_submit" class="button button-primary right" value="<?php esc_attr_e( 'Refund via HitPay Payment Gateway', 'mesomb-givewp' ); ?>"/>
                    </div>
                    <div class="clear"></div>
                </div>
                <?php
            }
        }

        public function admin_order_totals( $donationId )
        {
            $give_donation = get_post($donationId);
            if (give_get_payment_gateway($donationId) == 'mesomb') {

                $payment_method = '';
                $payment_request_id = give_get_payment_meta($donationId, 'HitPay_payment_request_id', true );

                if (!empty($payment_request_id)) {
                    $payment_method = give_get_payment_meta($donationId, 'HitPay_payment_method', true );
                    $fees = give_get_payment_meta($donationId, 'HitPay_fees', true );
                    if (empty($payment_method) || empty($fees)) {

                        $give_settings = give_get_settings();

                        try {
                            $mesomb_client = new Client(
                                $give_settings['mesomb_api_key'],
                                $this->getMode($give_settings['mesomb_mode'])
                            );

                            $paymentStatus = $mesomb_client->getPaymentStatus($payment_request_id);
                            if ($paymentStatus) {
                                $payments = $paymentStatus->payments;
                                if (isset($payments[0])) {
                                    $payment = $payments[0];
                                    $payment_method = $payment->payment_type;
                                    $fees = $payment->fees;
                                    give_update_payment_meta($donationId, 'HitPay_payment_method', $payment_method);
                                    give_update_payment_meta($donationId, 'HitPay_fees', $fees);
                                }
                            }
                        } catch (\Exception $e) {
                            $payment_method = $e->getMessage();
                        }
                    }
                }

                if (!empty($payment_method)) {
                    $HitPay_currency = give_update_payment_meta($donationId, 'HitPay_currency', true );
                    ?>
                    <table class="wc-order-totals" style="margin:12px; padding:12px">
                        <tbody>
                        <tr>
                            <td class="label"><?php echo __('HitPay Payment Type', 'mesomb-givewp') ?>:</td>
                            <td width="1%"></td>
                            <td class="total">
                                <span class="woocommerce-Price-amount amount"><bdi><?php echo ucwords(str_replace("_", " ", $payment_method)) ?></bdi></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label"><?php echo __('HitPay Fee', 'mesomb-givewp') ?>:</td>
                            <td width="1%"></td>
                            <td class="total">
									<span class="woocommerce-Price-amount amount">
										<bdi>
										<?php echo give_currency_filter($fees, $HitPay_currency); ?>
										</bdi>
									</span>
                            </td>
                        </tr>

                        </tbody>
                    </table>
                    <?php
                }
            }
        }

        public function give_mesomb_process_refund($donationId)
        {
            if (isset($_POST['mesomb_payment_refund_submit'])) {
                $amount = give_donation_amount($donationId);
                $amountValue = number_format($amount, 2, '.', '');

                try {
                    $HitPay_transaction_id = give_get_payment_meta($donationId, 'HitPay_transaction_id', true );
                    $HitPay_is_refunded = give_get_payment_meta($donationId, 'HitPay_is_refunded', true );
                    if ($HitPay_is_refunded == 1) {
                        throw new Exception(__('Only one refund allowed per transaction by HitPay Payment Gateway.',  'mesomb-givewp'));
                    }

                    $give_settings = give_get_settings();

                    $mesombClient = new Client(
                        $give_settings['mesomb_api_key'],
                        $this->getMode($give_settings['mesomb_mode'])
                    );

                    $result = $mesombClient->refund($HitPay_transaction_id, $amountValue);

                    give_update_payment_meta($donationId, 'HitPay_is_refunded', 1);
                    give_update_payment_meta($donationId, 'HitPay_refund_id', $result->getId());
                    give_update_payment_meta($donationId, 'HitPay_refund_amount_refunded', $result->getAmountRefunded());
                    give_update_payment_meta($donationId, 'HitPay_refund_created_at', $result->getCreatedAt());

                    $message = __('Refund successful. Refund Reference Id: '.$result->getId().', '
                        . 'Payment Id: '.$HitPay_transaction_id.', Amount Refunded: '.$result->getAmountRefunded().', '
                        . 'Payment Method: '.$result->getPaymentMethod().', Created At: '.$result->getCreatedAt(), 'mesomb-givewp');

                    $totalRefunded = $result->getAmountRefunded();
                    if ($totalRefunded) {
                        give_update_payment_status($donationId, 'refunded');
                        give_insert_payment_note($donationId, $message);
                    }

                    return;
                } catch (\Exception $e) {
                    $message = $e->getMessage().'<br/><br/>';
                    $link = admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-payment-details&give-messages[]=payment-updated&id=' . $donationId );
                    $message .= '<a href="'.$link.'">Go to Donation Payment Page</a>';
                    wp_die(
                        $message,
                        esc_html__( 'Error', 'mesomb-givewp' ),
                        [
                            'response' => 400,
                        ]
                    );
                }
            }
        }

        /**
         * Render customized label.
         *
         * @param $field
         * @param $settings
         */
        public function render_mesomb_label($field, $settings)
        {
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field['id']); ?>"><?php echo $field['title']; ?></label>
                </th>
                <td class="give-forminp give-forminp-<?php echo sanitize_title($field['type']) ?>">
                    <span style="<?php echo isset($field['style']) ? $field['style'] :''; ?> font-weight: 700;"><?php echo $field['default']; ?></span>
                </td>
            </tr>
            <?php
        }

        /**
         * Render customized title.
         *
         * @param $field
         * @param $settings
         */
        public function render_mesomb_title($field, $settings)
        {
            $current_tab = give_get_current_setting_tab();

            if ($field['table_html']) {
                echo $field['id'] === "mesomb_module_information" ? '<table class="form-table">' . "\n\n" : '';
            }
            ?>
            <tr valign="top">
                <th scope="row" style="padding: 0px">
                    <div class="give-setting-tab-header give-setting-tab-header-<?php echo $current_tab; ?>">
                        <h2><?php echo $field['title']; ?></h2>
                        <hr>
                    </div>
                </th>
            </tr>
            <?php
        }

        /**
         * Register Admin Settings.
         *
         * @param array $settings List
         *
         * @return array
         */
        function register_settings($settings)
        {
            switch (give_get_current_setting_section()) {
                case 'mesomb':
                    $settings = array(
                        array(
                            'id'    => 'mesomb_module_information',
                            'type'  => 'mesomb_title',
                            'title' => __('API Credentials', 'mesomb-for-give')
                        ),
//                        array(
//                            'id'      => 'mesomb_mode',
//                            'name'    => __( 'Live Mode', 'mesomb-give' ),
//                            'type'    => 'radio_inline',
//                            'options' => array(
//                                'yes' => __( 'Live', 'mesomb-give' ),
//                                'no' => __( 'Sandbox', 'mesomb-give' )
//                            ),
//                            'default' => 'no',
//                        ),
                        array(
                            'id'      => 'mesomb_application',
                            'type'    => 'text',
                            'name'    => __('MeSomb Application Key', 'mesomb-for-give'),
                            'desc'    => __('Copy/Paste values from MeSomb Dashboard under Applications -> Your Application > Integration', 'mesomb-for-give'),
                            'default' => ''
                        ),
                        array(
                            'id'      => 'mesomb_access_key',
                            'type'    => 'text',
                            'name'    => __('MeSomb Access Key', 'mesomb-give'),
                            'desc'    => __('Copy/Paste values from MeSomb Dashboard under Applications -> Your Application > Integration', 'mesomb-for-give'),
                            'default' => ''
                        ),
                        array(
                            'id'      => 'mesomb_secret_key',
                            'type'    => 'text',
                            'name'    => __('MeSomb Secret Key', 'mesomb-give'),
                            'desc'    => __('Copy/Paste values from MeSomb Dashboard under Applications -> Your Application > Integration', 'mesomb-for-give'),
                            'default' => ''
                        ),
//                        array(
//                            'id'      => 'mesomb_fees_included',
//                            'name'    => __( 'Fees Included', 'mesomb-for-give' ),
//                            'type'    => 'radio_inline',
//                            'desc' => __('Fees are already included in the displayed price', 'mesomb-for-give'),
//                            'options' => array(
//                                'yes' => __( 'Yes', 'mesomb-for-give' ),
//                                'no' => __( 'No', 'mesomb-for-give' )
//                            ),
//                            'default' => 'yes',
//                        ),
                        array(
                            'id'      => 'mesomb_conversion',
                            'name'    => __( 'Currency Conversion', 'mesomb-for-give' ),
                            'type'    => 'radio_inline',
                            'desc' => __('Rely on MeSomb to automatically convert foreign currencies', 'mesomb-for-give'),
                            'options' => array(
                                'yes' => __( 'Yes', 'mesomb-for-give' ),
                                'no' => __( 'No', 'mesomb-for-give' )
                            ),
                            'default' => 'yes',
                        ),
                        array(
                            'id'      => 'mesomb_countries',
                            'name'    => __( 'Countries', 'mesomb-for-give' ),
                            'type'    => 'multiselect',
                            'desc' => __('You can receive payments from which countries', 'mesomb-for-give'),
                            'options' => array(
                                'CM' => __('Cameroon', 'mesomb-for-give'),
                                'NE' => __('Niger', 'mesomb-for-give')
                            ),
                            'default' => ['CM'],
                        ),
                        array(
                            'id'   => 'give_title_mesomb',
                            'type' => 'sectionend'
                        ),
                    );

                    break;
            }

            return $settings;
        }

        /**
         * Register Section for Payment Gateway Settings.
         *
         * @param array $sections List of sections
         *
         * @return mixed
         */
        public function register_sections($sections)
        {
            $sections['mesomb'] = 'MeSomb';

            return $sections;
        }
    }
}

new MeSomb_Gateway_Admin_Settings();