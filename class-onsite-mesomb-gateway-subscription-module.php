<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\SubscriptionComplete;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;

/**
 * @inheritDoc
 */
class MeSombGatewayOnsiteSubscriptionModuleClass extends SubscriptionModule
{
    /**
     * @inerhitDoc
     *
     * @throws Exception|PaymentGatewayException
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData
    ) {
        try {
            // Step 1: Validate any data passed from the gateway fields in $gatewayData.  Throw the PaymentGatewayException if the data is invalid.
            if (empty($gatewayData['mesomb-for-give'])) {
                throw new PaymentGatewayException(__('MeSomb payment ID is required.', 'mesomb-for-give'));
            }

            // Step 2: Create a subscription with your gateway.
            $response = MeSombGatewayApi::createSubscription(['transaction_id' => $gatewayData['mesomb-for-give']]);

            // Step 3: Return a command to complete the subscription. You can alternatively return SubscriptionProcessing for gateways that require a webhook or similar to confirm that the subscription is complete. SubscriptionProcessing will trigger an email notification, configurable in the settings.
            return new SubscriptionComplete($response['transaction_id'], $response['id']);
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
     *
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(Subscription $subscription)
    {
        try {
            // Step 1: cancel the subscription with your gateway.
            MeSombGatewayApi::cancelSubscription($subscription->gatewaySubscriptionId);

            // Step 2: update the subscription status to cancelled.
            $subscription->status = SubscriptionStatus::CANCELLED();
            $subscription->save();
        } catch (\Exception $exception) {
            throw new PaymentGatewayException(
                sprintf(
                    'Unable to cancel subscription. %s',
                    esc_html($exception->getMessage())
                ),
                esc_html($exception->getCode()),
                $exception
            );
        }
    }
}