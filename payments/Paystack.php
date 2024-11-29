<?php
namespace Joytekmotion\Paystack\Payments;

use Admin\Classes\BasePaymentGateway;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Traits\EventEmitter;
use Igniter\PayRegister\Traits\PaymentHelpers;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Joytekmotion\Paystack\Classes\PaystackApi;

class Paystack extends BasePaymentGateway {
    use PaymentHelpers;
    use EventEmitter;

    protected $sessionKey = 'ti_joytekmotion_paystack';

    public function registerEntryPoints()
    {
        return [
            'paystack_initialize_transaction' => 'initializeTransaction',
            'paystack_process_transaction' => 'processTransaction',
            'paystack_payment_successful' => 'paymentSuccessful',
            'paystack_webhook' => 'processWebhookUrl',
            'paystack_cancel_url' => 'paymentCancelled'
        ];
    }

    public function isApplicable($total, $host)
    {
        return $host->order_total <= $total;
    }

    public function beforeRenderPaymentForm($host, $controller)
    {
        $controller->addJs('https://js.paystack.co/v2/inline.js', 'paystack-inline-js');
        $controller->addJs('$/joytekmotion/paystack/assets/js/process.paystack.js', 'process-paystack-js');
    }

    public function initializeTransaction($order) {
        if (!($order instanceof \Admin\Models\Orders_model)) {
            $order = OrderManager::instance()->getOrder();
        }

        Session::forget($this->sessionKey.'.create_payment_profile');

        if(post('create_payment_profile')) {
            Session::put($this->sessionKey.'.create_payment_profile', true);
        }

        try {
            $metadata = $this->getMetadata($order);
            $metadata['cancel_action'] = $this->makeEntryPointUrl('paystack_cancel_url').'/'.$order->hash;
            $data = [
                'email' => $order->email,
                'amount' => (int)($order->order_total * 100),
                'currency' => currency()->getUserCurrency(),
                'metadata' => json_encode($metadata),
            ];

            if ($this->getIntegrationType() == 'redirect')
                $data['callback_url'] = $this->makeEntryPointUrl('paystack_payment_successful').'/'.$order->hash;

            $response = $this->createGateway()->initializeTransaction($data);
            return $response['data'] ?? [];
        } catch (\Exception $ex) {
            throw new ApplicationException($ex->getMessage());
        }
    }

    protected function getCustomFields($order) {
        $customFields = [
            [
                'display_name' => 'Invoice ID',
                'variable_name' => 'invoice_id',
                'value' => $order->order_id,
            ],
            [
                'display_name' => 'Customer Name',
                'variable_name' => 'customer_name',
                'value' => $order->first_name .' '. $order->last_name,
            ],
            [
                'display_name' => 'Customer Email',
                'variable_name' => 'customer_email',
                'value' => $order->email,
            ],
            [
                'display_name' => 'Customer Phone',
                'variable_name' => 'customer_phone',
                'value' => $order->telephone,
            ],
            [
                'display_name' => 'Order Hash',
                'variable_name' => 'order_hash',
                'value' => $order->hash,
            ],
        ];

        $eventResult = $this->fireSystemEvent('joytekmotion.paystack.extendCustomFields',
            [$customFields, $order], false);

        if (is_array($eventResult))
            $customFields = array_merge($customFields, ...$eventResult);

        return $customFields;
    }

    protected function getMetadata($order) {
        $metadata = [
            'custom_fields' => $this->getCustomFields($order),
        ];

        $eventResult = $this->fireSystemEvent('joytekmotion.paystack.extendMetadata',
            [$metadata, $order], false);

        if (is_array($eventResult))
            $metadata = array_merge($metadata, ...$eventResult);

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function payFromPaymentProfile($order, $data = [])
    {
        $profile = $this->model->findPaymentProfile($order->customer);
        if(!$profile || !array_has($profile->profile_data, 'authorization_code'))
            throw new ApplicationException(
                lang('joytekmotion.paystack::default.alert_payment_profile_not_found')
            );

        $metadata = $this->getMetadata($order);
        $metadata['cancel_action'] = $this->makeEntryPointUrl('paystack_cancel_url').'/'.$order->hash;

        $data = [
            'email' => $order->email,
            'amount' => (int)($order->order_total * 100),
            'currency' => currency()->getUserCurrency(),
            'authorization_code' => $profile->profile_data['authorization_code'],
            'metadata' => json_encode($metadata),
        ];

        try {
            $response = $this->createGateway()->chargeAuthorization($data);

            if(array_get($response, 'data.paused'))
                return Redirect::to(array_get($response, 'data.authorization_url'));

            if(array_get($response, 'data.status') != 'success')
                throw new ApplicationException(array_get($response, 'message'));

            $order->logPaymentAttempt(
                lang('joytekmotion.paystack::default.alert_payment_successful'),
                1, $data, $response, true
            );
            $order->updateOrderStatus($this->model->order_status, ['notify' => FALSE]);
            $order->markAsPaymentProcessed();
        } catch(\Exception $ex) {
            $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_payment_error', [
                'message' => $ex->getMessage()
            ]), 0, $data, $response ?? []);
            throw new ApplicationException($ex->getMessage());
        }
    }

    public function deletePaymentProfile($customer, $profile) { }

    /**
     * @throws ApplicationException
     */
    public function processWebhookUrl() {
        $this->validateWebhookRequest();

        if (post('event') != 'charge.success') return;

        $orderHash = null;
        foreach(post('data')['metadata']['custom_fields'] as $field) {
            if ($field['variable_name'] == 'order_hash') {
                $orderHash = $field['value'];
                break;
            }
        }

        if (!$orderHash) return;

        $order = $this->createOrderModel()->whereHash($orderHash)->first();
        if (!$order) return;

        if($order->isPaymentProcessed()) return;

        if(post('data.amount') != (int) ($order->order_total * 100)) return;

        if((post('data.status') == 'success')) {
            $order->updateOrderStatus($this->model->order_status, ['notify' => FALSE]);
            $order->markAsPaymentProcessed();
            $order->logPaymentAttempt(
                lang('joytekmotion.paystack::default.alert_payment_successful'),
                1, post(), post('data'), true
            );
        } else {
            $order->logPaymentAttempt(post('data.message'), 0, post(), post('data'));
        }
    }

    protected function validateWebhookRequest() {
        $ipWhitelist = ['52.31.139.75', '52.49.173.169', '52.49.173.169'];

        if (!in_array(request()->ip(), $ipWhitelist))
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_invalid_request', [
                'message' => 'IP not whitelisted'
            ]));

        $requestMethod = request()->getMethod();
        $signature = request()->header('x-paystack-signature');

        if (strtoupper($requestMethod) != 'POST' || !$signature)
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_invalid_request', [
                'message' => 'Invalid request method or signature'
            ]));

        $input = request()->getContent();
        $secretKey = $this->getSecretKey();

        if($signature !== hash_hmac('sha512', $input, $secretKey))
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_invalid_request', [
                'message' => 'Invalid signature'
            ]));
    }

    public function paymentSuccessful() {
        $order = OrderManager::instance()->getOrder();
        try {
            if (!$order)
                throw new \Exception(lang('joytekmotion.paystack::default.alert_transaction_failed'));

            if (!$order->isPaymentProcessed()) {
                $response = $this->createGateway()->verifyTransaction(post('reference'));

                $orderHash = null;
                $customFields = array_get($response, 'data.metadata.custom_fields');
                if (is_array($customFields)) {
                    foreach($customFields as $field) {
                        if ($field['variable_name'] == 'order_hash') {
                            $orderHash = $field['value'];
                            break;
                        }
                    }
                }

                if($orderHash != $order->hash)
                    throw new \Exception(lang('joytekmotion.paystack::default.alert_order_hash_mismatch'));

                if(array_get($response, 'data.amount') != (int) ($order->order_total * 100))
                    throw new \Exception(lang('joytekmotion.paystack::default.alert_amount_mismatch'));

                if (array_get($response, 'data.status') == 'success')
                {
                    $order->updateOrderStatus($this->model->order_status, ['notify' => FALSE]);
                    $order->markAsPaymentProcessed();
                    $order->logPaymentAttempt(
                        lang('joytekmotion.paystack::default.alert_payment_successful'),
                        1, post(), $response, true
                    );
                }

                $createPaymentProfile = Session::get($this->sessionKey.'.create_payment_profile');

                if($createPaymentProfile && array_get($response, 'data.authorization.reusable')) {
                    $this->updatePaymentProfile($order->customer, array_get($response, 'data.authorization'));
                }
                Session::forget($this->sessionKey.'.create_payment_profile');

                if($this->getIntegrationType() == 'redirect')
                    return Redirect::to(page_url('checkout'.DIRECTORY_SEPARATOR.'checkout'));
            }

        } catch(\Exception $ex) {
            $order->logPaymentAttempt($ex->getMessage(), 0, post(), $response ?? []);
            flash()->warning(lang('joytekmotion.paystack::default.alert_transaction_failed'))
                ->important()->now();
        }
    }

    public function updatePaymentProfile($customer, $data)
    {
        return $this->handleUpdatePaymentProfile($customer, $data);
    }

    protected function handleUpdatePaymentProfile($customer, $data) {
        $profile = $this->model->findPaymentProfile($customer);
        $profileData = $profile ? (array)$profile->profile_data : $data;

        if (!$profile)
            $profile = $this->model->initPaymentProfile($customer);

        $profile->card_brand = strtolower(array_get($profileData, 'card_type'));
        $profile->card_last4 = array_get($profileData, 'last4');
        $profile->setProfileData($profileData);

        return $profile;
    }

    protected function updatePaymentProfileData($profile, $profileData = [], $cardData = [])
    {
        $profile->card_brand = strtolower(array_get($cardData, 'card.brand'));
        $profile->card_last4 = array_get($cardData, 'card.last4');
        $profile->setProfileData($profileData);

        return $profile;
    }

    public function paymentCancelled($params) {
        $hash = $params[0] ?? null;
        $order = $this->createOrderModel()->whereHash($hash)->first();
        try {
            if (!$order || !$order->isPaymentProcessed())
                throw new \Exception(lang('joytekmotion.paystack::default.alert_transaction_failed'));
        } catch(\Exception $ex) {
            flash()->warning($ex->getMessage())->important()->now();
        }
        return Redirect::to(page_url('checkout'.DIRECTORY_SEPARATOR.'checkout'));
    }

    protected function createGateway() {
        return new PaystackApi($this->getSecretKey());
    }

    protected function isTestMode() {
        return $this->model->transaction_mode != 'live';
    }

    protected function getSecretKey() {
        return $this->isTestMode() ? $this->model->test_secret_key : $this->model->live_secret_key;
    }

    public function getIntegrationType() {
        return $this->model->integration_type ?? 'popup';
    }

    public function processPaymentForm($data, $host, $order) {
        $this->validatePaymentMethod($order, $host);

        if ($this->getIntegrationType() == 'popup')
            return;

        try {
            $response = $this->initializeTransaction($order);
            if (!array_has($response, 'authorization_url'))
                throw new ApplicationException(lang('joytekmotion.paystack::default.alert_transaction_failed'));

            return Redirect::to(array_get($response, 'authorization_url'));
        } catch (\Exception $ex) {
            $order->logPaymentAttempt(
                lang('joytekmotion.paystack::default.alert_payment_error', [
                    'message' => $ex->getMessage()
                ]), 0, [], post()
            );
            throw new ApplicationException($ex->getMessage());
        }
    }

    public function processRefundForm($data, $order, $paymentLog)
    {
        $paymentResponse = $paymentLog->response;
        if (!is_null($paymentLog->refunded_at) || !is_array($paymentResponse))
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_refund_nothing_to_refund'));

        if (array_get($paymentResponse, 'data.status') != 'success')
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_payment_not_settled'));

        $transactionId = array_get($paymentLog->response, 'data.reference');

        $refundAmount = array_get($data, 'refund_type') == 'full' ? $order->order_total :
            array_get($data, 'refund_amount');

        if ($refundAmount > $order->order_total)
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_refund_amount_should_be_less'));

        $response = [];
        try {
            $response = $this->createGateway()->createRefund($transactionId, (int)($refundAmount * 100));
            $status = array_get($response, 'status');
            if($status == 'success' || $status == 'pending') {
                $paymentLog->markAsRefundProcessed();
                $order->logPaymentAttempt(array_get($response, 'message'), 1, [], $response);
                return true;
            }
        } catch (\Exception $ex) {
            $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_refund_failed',
                ['message' => $ex->getMessage()]), 0, [], $response);
        }
    }

    public function isCardIconSupported($cardType) {
        $cards = ['visa', 'mastercard', 'paypal', 'amex', 'discover', 'diners-club', 'jcb', 'stripe'];
        return array_has($cards, $cardType);
    }

    public function supportsPaymentProfiles()
    {
        return true;
    }
}
