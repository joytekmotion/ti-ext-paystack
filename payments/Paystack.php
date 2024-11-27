<?php
namespace Joytekmotion\Paystack\Payments;

use Admin\Classes\BasePaymentGateway;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\PayRegister\Traits\PaymentHelpers;
use Joytekmotion\Paystack\Classes\PaystackApi;

class Paystack extends BasePaymentGateway {
    use PaymentHelpers;

    public function registerEntryPoints()
    {
        return [
            'paystack_initialize_transaction' => 'initializeTransaction',
            'paystack_process_transaction' => 'processTransaction',
            'paystack_payment_successful' => 'paymentSuccessful',
            'paystack_webhook' => 'processWebhookUrl'
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

    public function initializeTransaction() {
        $order = OrderManager::instance()->getOrder();
        try {
            $response = $this->createGateway()->initializeTransaction([
                'email' => $order->email,
                'amount' => $order->order_total * 100,
                'currency' => currency()->getUserCurrency(),
                'metadata' => json_encode([
                    'custom_fields' => [
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
                        ]
                    ]
                ]),
            ]);
            $response['data']['order_hash'] = $order->hash;
            return $response['data'];
        } catch (\Exception $ex) {
            throw new ApplicationException($ex->getMessage());
        }
    }

    public function processWebhookUrl() {
        if (post('event') != 'charge.success')
            return;

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




    }

    public function paymentSuccessful($params) {

        $hash = $params[0] ?? null;
        $order = $this->createOrderModel()->whereHash($hash)->first();
        try {
            if (!$order)
                throw new \Exception(lang('joytekmotion.paystack::default.alert_transaction_failed'));

            if (!$order->isPaymentProcessed() && (post('status') == 'success')) {
                $response = $this->createGateway()->verifyTransaction(post('reference'));
                if (($response['data']['status'] == 'success') &&
                    ($response['data']['amount'] == $order->order_total * 100)
                ) {
                    $order->updateOrderStatus($this->model->order_status, ['notify' => FALSE]);
                    $order->markAsPaymentProcessed();
                    $order->logPaymentAttempt(
                        lang('joytekmotion.paystack::default.alert_payment_successful'),
                        1, post(), $response, true
                    );
                }
            }

        } catch(\Exception $ex) {
            flash()->warning($ex->getMessage())->important()->now();
        }
    }

    public function paymentFailed($params) {
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

    public function getAuth($order) {
        try {
            return $this->createGateway()->auth([
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'terminal' => $this->getTerminalId(),
                'invoiceId' => $order->order_id,
                'amount' => $order->order_total,
                'currency' => currency()->getUserCurrency()
            ]);
        } catch (\Exception $ex) {
            flash()->warning($ex->getMessage())->important()->now();
            throw new ApplicationException($ex->getMessage());
        }
    }

    protected function getSecretKey() {
        return $this->isTestMode() ? $this->model->test_secret_key : $this->model->live_secret_key;
    }

    protected function getTransactionType() {
        return $this->model->transaction_type ?? 'SALE';
    }

    public function processPaymentResponse() {
        $order = $this->createOrderModel()->find(post('invoiceId'));
        try {
            if ($order && post('success')) {
                if (!$order->isPaymentProcessed()) {
                    $order->updateOrderStatus($this->model->order_status, ['notify' => FALSE]);
                    $order->markAsPaymentProcessed();
                    // save card
                    if (post('storeCardOnFile')) {
                        $this->handleUpdatePaymentProfile($order->customer, [
                            'merchantTokenId' => post('cardTokenId'),
                            'cardName' => post('cardholderName') .' - '. post('cardSchemeName'),
                            'panStar' => post('cardPanStarred'),
                            'expiryDate' => post('cardExpiryDate'),
                            'cardSchemeId' => post('cardSchemeId'),
                            'cardholderName' => post('cardholderName'),
                            'cardSchemeName' => post('cardSchemeName'),
                            'cardIssuingCountry' => post('cardIssuingCountry'),
                        ]);
                    }
                }

                if (post('settled')) {
                    $order->logPaymentAttempt(
                        lang('joytekmotion.paystack::default.alert_payment_successful'), 1, [], post(), true);
                } else {
                    $order->logPaymentAttempt(
                        lang('joytekmotion.paystack::default.alert_payment_authorized'), 1, [], post());
                }
            } else {
                $message = post('message') ?? lang('joytekmotion.paystack::default.alert_transaction_failed');
                throw new \Exception($message);
            }
        } catch (\Exception $ex) {
            if ($order)
                $order->logPaymentAttempt($ex->getMessage(), 0, [], post());
        }
    }

    public function getPaymentProfiles($customer) {
        if (!$customer)
            return [];
        if ($this->paymentProfiles !== null)
            return $this->paymentProfiles;
        return $this->paymentProfiles = Payment_profiles_model::where('customer_id', $customer->customer_id)
            ->where('payment_id', $this->model->payment_id)->get();
    }

    protected function cardExists($customer, $cardTokenId) {
        return Payment_profiles_model::where('customer_id', $customer->customer_id)
            ->where('payment_id', $this->model->payment_id)
            ->where('profile_data->merchantTokenId', $cardTokenId)->exists();
    }

    public function processDeletePaymentProfile($params)
    {
        if (isset($params[0])) {
            $profile = Payment_profiles_model::where('customer_id', Auth::getUser()->customer_id)
                ->where('payment_profile_id', $params[0])
                ->first();

            if ($profile) {
                return $profile->delete();
            }
        }
    }

    /**
     * Settle an authorized payment
     * @param string $transactionId
     * @param Orders_model $order
     * @return bool
     *
     * @throws ApplicationException
     */
    public function settleAuthPayment($transactionId, $order) {
        try {
            $result = $this->createGateway()->charge([
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'terminal' => $this->getTerminalId(),
                'invoiceId' => $order->order_id,
                'amount' => $order->order_total,
                'currency' => currency()->getUserCurrency(),
                'transaction_id' => $transactionId
            ]);
            if( !empty($result) && $result['success'] ) {
                $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_payment_successful'), 1, [], $result, true);
                return true;
            }
        } catch (\Exception $ex) {
            throw new ApplicationException($ex->getMessage());
        }
        return false;
    }

    /**
     * Cancel an authorized payment
     * @param $transactionId
     * @param $order
     * @return bool
     * @throws ApplicationException
     */
    public function cancelAuthPayment($transactionId, $order)
    {
        try {
            $result = $this->createGateway()->cancel([
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'terminal' => $this->getTerminalId(),
                'invoiceId' => $order->order_id,
                'amount' => $order->order_total,
                'currency' => currency()->getUserCurrency(),
                'transaction_id' => $transactionId
            ]);
            if( !empty($result) && $result['success'] ) {
                $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_payment_cancelled'), 1, [], $result);
                return true;
            }
        } catch (\Exception $ex) {
            $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_payment_error', ['message' => $ex->getMessage()]), 0, [], $result);
        }
        return false;
    }

    public function processRefundForm($data, $order, $paymentLog)
    {
        $paymentResponse = $paymentLog->response;
        if (!is_null($paymentLog->refunded_at) || !is_array($paymentResponse))
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_refund_nothing_to_refund'));

        if (!(
                (array_get($paymentResponse, 'success') && array_get($paymentResponse, 'settled')) ||
                (array_get($paymentResponse, 'transactionState') == 'CHARGE')
            ))
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_payment_not_settled'));

        $transactionId = array_get($paymentLog->response, 'id');

        $refundAmount = array_get($data, 'refund_type') == 'full' ? $order->order_total :
            array_get($data, 'refund_amount');

        if ($refundAmount > $order->order_total)
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_refund_amount_should_be_less'));

        $result = [];
        try {
            $result = $this->createGateway()->refund([
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'terminal' => $this->getTerminalId(),
                'invoiceId' => $order->order_id,
                'amount' => $refundAmount,
                'currency' => currency()->getUserCurrency(),
                'transaction_id' => $transactionId
            ]);
            if( !empty($result) && $result['success'] ) {
                $paymentLog->markAsRefundProcessed();
                $order->logPaymentAttempt(
                    lang('joytekmotion.paystack::default.alert_payment_refunded', [
                        'transactionId' => $transactionId,
                        'amount' => currency_format($refundAmount)
                    ]), 1, [], $result);
                return true;
            }
        } catch (\Exception $ex) {
            $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_refund_failed',
                ['message' => $ex->getMessage()]), 0, [], $result);
        }

    }
}
