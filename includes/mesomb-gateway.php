<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentComplete;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;


class MeSombGiveSignature
{
    /**
     * @param string $service service to use can be payment, wallet ... (the list is provide by MeSomb)
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE...)
     * @param string $url the full url of the request with query element https://mesomb.hachther.com/path/to/ressource?highlight=params#url-parsing
     * @param \DateTime $date Datetime of the request
     * @param string $nonce Unique string generated for each request sent to MeSomb
     * @param array $credentials dict containing key => value for the credential provided by MeSOmb. {'access' => access_key, 'secret' => secret_key}
     * @param array $headers Extra HTTP header to use in the signature
     * @param array|null $body The dict containing the body you send in your request body
     * @return string Authorization to put in the header
     */
    public static function signRequest($service, $method, $url, $date, $nonce, $credentials, $headers = [], $body = null)
    {
        $algorithm = 'HMAC-SHA1';
        $parse = wp_parse_url($url);
        $canonicalQuery = isset($parse['query']) ? $parse['query'] : '';

        $timestamp = $date->getTimestamp();

        if (!isset($headers)) {
            $headers = [];
        }
        $headers['host'] = $parse['scheme']."://".$parse['host'].(isset($parse['port']) ? ":".$parse['port'] : '');
        $headers['x-mesomb-date'] = $timestamp;
        $headers['x-mesomb-nonce'] = $nonce;
        ksort($headers);
        $callback = function ($k, $v) {
            return strtolower($k) . ":" . $v;
        };
        $canonicalHeaders = implode("\n", array_map($callback, array_keys($headers), array_values($headers)));

        if (!isset($body)) {
            $body = "{}";
        } else {
            $body = wp_json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        $payloadHash = sha1($body);

        $signedHeaders = implode(";", array_keys($headers));

        $path = implode("/", array_map("rawurlencode", explode("/", $parse['path'])));
        $canonicalRequest = $method."\n".$path."\n".$canonicalQuery."\n".$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;

        $scope = $date->format("Ymd")."/".$service."/mesomb_request";
        $stringToSign = $algorithm."\n".$timestamp."\n".$scope."\n".sha1($canonicalRequest);

        $signature = hash_hmac('sha1', $stringToSign, $credentials['secretKey'], false);
        $accessKey = $credentials['accessKey'];

        return "$algorithm Credential=$accessKey/$scope, SignedHeaders=$signedHeaders, Signature=$signature";
    }

    /**
     * Generate a random string by the length
     *
     * @param int $length
     * @return string
     */
    public static function nonceGenerator($length = 40) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[wp_rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

/**
 * @inheritDoc
 */
class MeSombGatewayOnsiteClass extends PaymentGateway
{
    /**
     * @var mixed
     */
    private $application;
    /**
     * @var mixed
     */
    private $accessKey;
    /**
     * @var mixed
     */
    private $secretKey;
    /**
     * @var mixed
     */
    private $conversion;

    public function __construct()
    {
        parent::__construct();
        $this->application = give_get_option('mesomb_application');
        $this->accessKey = give_get_option('mesomb_access_key');
        $this->secretKey = give_get_option('mesomb_secret_key');
        $this->conversion = give_get_option('mesomb_conversion');
    }

    /**
     * @inheritDoc
     */
    public static function id(): string
    {
        return 'mesomb-for-give';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return self::id();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return __('MeSomb for Give', 'mesomb-for-give');
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodLabel(): string
    {
        return __('MeSomb for Give', 'mesomb-for-give');
    }


    /**
     * Display gateway fields for v2 donation forms
     */
    public function getLegacyFormFieldMarkup(int $formId, array $args): string
    {
        // Step 1: add any gateway fields to the form using html.  In order to retrieve this data later the name of the input must be inside the key gatewayData (name='gatewayData[input_name]').
        // Step 2: you can alternatively send this data to the $gatewayData param using the filter `givewp_create_payment_gateway_data_{gatewayId}`.
        return "<div><input type='text' name='gatewayData[mesomb-for-give]' placeholder='MeSomb gateway field' /></div>";
    }

    /**
     * Register a js file to display gateway fields for v3 donation forms
     */
    public function enqueueScript(int $formId)
    {
        wp_enqueue_script('mesomb-gateway', plugin_dir_url(__FILE__) . '../js/mesomb-gateway.js', ['react', 'wp-element'], '1.0.0', true);
        wp_enqueue_style( 'mesomb-gateway', plugins_url('../css/style.css', __FILE__), array(), '1.0.0', 'all' );
    }

    /**
     * Send form settings to the js gateway counterpart
     */
    public function formSettings(int $formId): array
    {
        return [
            'clientKey' => '1234567890',
            'providers' => array(
                array(
                    'key' => 'MTN',
                    'name' => 'Mobile Money',
                    'icon' => plugins_url('../images/logo-momo.png', __FILE__),
                    'countries' => array('CM')
                ),
                array(
                    'key' => 'ORANGE',
                    'name' => 'Orange Money',
                    'icon' => plugins_url('../images/logo-orange.jpg', __FILE__),
                    'countries' => array('CM')
                ),
                array(
                    'key' => 'AIRTEL',
                    'name' => 'Airtel Money',
                    'icon' => plugins_url('../images/logo-airtel.jpg', __FILE__),
                    'countries' => array('NE')
                )
            ),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createPayment(Donation $donation, $gatewayData): GatewayCommand
    {
        try {
            // Step 1: Validate any data passed from the gateway fields in $gatewayData.  Throw the PaymentGatewayException if the data is invalid.
            if (empty($gatewayData['mesomb-for-give'])) {
                throw new PaymentGatewayException(__('MeSomb payment ID is required.', 'mesomb-for-give' ) );
            }

            // Step 2: Create a payment with your gateway.
            $response = $this->meSombRequest($donation, [
                'transaction_id' => $gatewayData['mesomb-for-give'],
                'payer' => $gatewayData['payer'],
                'service' => $gatewayData['service'],
            ]);

            // Step 3: Return a command to complete the donation. You can alternatively return PaymentProcessing for gateways that require a webhook or similar to confirm that the payment is complete. PaymentProcessing will trigger a Payment Processing email notification, configurable in the settings.
            return new PaymentComplete($response['transaction_id']);
        } catch (Exception $e) {
            // Step 4: If an error occurs, you can update the donation status to something appropriate like failed, and finally throw the PaymentGatewayException for the framework to catch the message.
            $errorMessage = $e->getMessage();

            $donation->status = DonationStatus::FAILED();
            $donation->save();

            DonationNote::create([
                'donationId' => $donation->id,
                /* translators: %s: reason of failure */
                'content' => sprintf(esc_html__('Donation failed. Reason: %s', 'mesomb-for-give'), $errorMessage)
            ]);

            throw new PaymentGatewayException(esc_html($errorMessage));
        }
    }

    /**
     * @inerhitDoc
     */
    public function refundDonation(Donation $donation): PaymentRefunded
    {
        // Step 1: refund the donation with your gateway.
        // Step 2: return a command to complete the refund.
        return new PaymentRefunded();
    }

    private function get_authorization($method, $url, $date, $nonce, array $headers = [], array $body = null)
    {
        $credentials = ['accessKey' => $this->accessKey, 'secretKey' => $this->secretKey];

        return MeSombGiveSignature::signRequest('fundraising', $method, $url, $date, $nonce, $credentials, $headers, $body);
    }


    /**
     * MeSomb request to gateway
     */
    private function meSombRequest(Donation $donation, array $data): array
    {
        $locale = substr(get_bloginfo('language'), 0, 2);

        $amount = $donation->intendedAmount();
        $country = 'CM';
        $dataDonation = $donation->toArray();
        $donor = $donation->donor()->get()->toArray();
//        print_r($dataDonation);
//        exit(0);
        $data = array(
            'amount' => intval($amount->formatToDecimal()),
            'payer' => $data['payer'],
            'service' => $data['service'],
//            'fees' => $this->fees_included == 'yes',
            'conversion' => $this->conversion == 'yes',
            'currency' => $amount->getCurrency(),
            'message' => $dataDonation['comment'],
            'anonymous' => $donation->anonymous ?? false,
//            'reference' => $dataDonation['id'],
            'country' => $country,
            'contributor' => array(
                'first_name' => $dataDonation['firstName'],
                'last_name' => $dataDonation['lastName'],
                'town' => $dataDonation['billingAddress']->city,
                'region' => $dataDonation['billingAddress']->state,
                'country' => $dataDonation['billingAddress']->country,
                'email' => $dataDonation['email'],
                'phone' => $dataDonation['phone'],
                'address_1' => $dataDonation['billingAddress']->address1,
                'postcode' => $dataDonation['billingAddress']->zip,
            ),
            'location' => array(
                'ip' => $donation->donorIp,
            ),
//            'products' => $products,
            'source' => 'WordPress/v'.get_bloginfo('version')
        );
        $lang = $locale == 'fr' ? 'fr' : 'en';

        /*
             * Your API interaction could be built with wp_remote_post()
             */
        $version = 'v1.1';
        $endpoint = 'contribute/';
        $url = "https://mesomb.hachther.com/api/$version/fundraising/$endpoint";
        $url = "http://host.docker.internal:8000/api/$version/fundraising/$endpoint";

        $headers = array(
            'Accept-Language' => $lang,
            'Content-Type'     => 'application/json',
            'X-MeSomb-Fund' => $this->application,
            'X-MeSomb-TrxID' => $dataDonation['id'],
        );

        $nonce = MeSombGiveSignature::nonceGenerator();
        $date = new DateTime();
        $authorization = $this->get_authorization('POST', $url, $date, $nonce, ['content-type' => 'application/json'], $data);

        $headers['x-mesomb-date'] = $date->getTimestamp();
        $headers['x-mesomb-nonce'] = $nonce;
        $headers['Authorization'] = $authorization;

        $response = wp_remote_post($url, array(
            'body' => wp_json_encode($data),
            'headers' => $headers
        ));

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if (isset($body['status']) && $body['status'] == 'SUCCESS') {
                $donation->gatewayTransactionId = $body['transaction']['pk'];
                $donation->save();

                return array_merge([
                    'success' => true,
                    'transaction_id' => $body['transaction']['pk'],
//                    'subscription_id' => $body['subscription_id'],
                ], $data);
            } else {
                throw new Exception(esc_html(isset($body['detail']) ? $body['detail'] : $body['message']));
            }
        } else {
            throw new Exception(esc_html__("Error during the payment process!\nPlease try again and contact the admin if the issue is continue", 'mesomb-for-give'));
        }
    }
}
