<?php
namespace Joytekmotion\Paystack\Payments;

use Admin\Classes\BasePaymentGateway;
use Igniter\PayRegister\Traits\PaymentHelpers;

class Paystack extends BasePaymentGateway {
    use PaymentHelpers;

    public function registerEntryPoints()
    {
        return [
        ];
    }

    public function isApplicable($total, $host)
    {
        return $host->order_total <= $total;
    }

    public function beforeRenderPaymentForm($host, $controller)
    {
        $controller->addJs('$/joytekmotion/paystack/assets/js/process.paystack.js', 'process-paystack-js');
    }

    public function processPaymentForm($data, $host, $order) {
        $this->validatePaymentMethod($order, $host);
        try {
            if ($order->isPaymentProcessed()) {
                return true;
            }
            $auth = $this->getAuth($order);
            $redirectUrl = $this->createGateway()
                ->generateUrl([
                    'invoiceId' => $order->order_id,
                    'amount' => $order->order_total,
                    'backLink' => $this->makeEntryPointUrl('dnapayments_payment_successful').'/'.$order->hash,
                    'failureBackLink' => $this->makeEntryPointUrl('dnapayments_payment_failed').'/'.$order->hash,
                    'postLink' => $this->makeEntryPointUrl('dnapayments_process_payment_response').'/'.$order->hash,
                    'failurePostLink' => $this->makeEntryPointUrl('dnapayments_process_payment_response').'/'.$order->hash,
                    'language' => $this->model->locale_code ?? app()->getLocale(),
                    'description' => '',
                    'accountId' => $order->customer_id,
                    'phone' => $order->telephone,
                    'terminal' => $this->getTerminalId(),
                    'currency' => currency()->getUserCurrency(),
                    'accountCountry' => $order->address ? $order->address->country->iso_code_2 : null,
                    'accountCity' => optional($order->address)->city,
                    'accountStreet1' => optional($order->address)->address_1,
                    'accountEmail' => $order->email,
                    'accountFirstName' => $order->first_name,
                    'accountLastName' => $order->last_name,
                    'accountPostalCode' => optional($order->address)->postcode,
                    'transactionType' => $this->getTransactionType(),
                ], $auth);
            return Redirect::to($redirectUrl);
        }
        catch (\Exception $ex) {
            $order->logPaymentAttempt(lang('joytekmotion.paystack::default.alert_payment_error', ['message' => $ex->getMessage()]), 0, [], $data);
            throw new ApplicationException(lang('joytekmotion.paystack::default.alert_transaction_failed'));
        };
    }

    public function paymentSuccessful($params) {
        $hash = $params[0] ?? null;
        $order = $this->createOrderModel()->whereHash($hash)->first();
        try {
            if (!$order)
                throw new \Exception(lang('joytekmotion.paystack::default.alert_transaction_failed'));

            if (!$order->isPaymentProcessed())
                flash()->warning(lang('joytekmotion.paystack::default.alert_payment_processing'))->important()->now();
        } catch(\Exception $ex) {
            flash()->warning($ex->getMessage())->important()->now();
        }
        return Redirect::to(page_url('checkout'.DIRECTORY_SEPARATOR.'checkout'));
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
        return new \DNAPayments\DNAPayments([
            'isTestMode' => $this->isTestMode(),
            'scopes' => [
                'allowSeamless' => true,
                'webapi' => true
            ],
            'allowSavingCards' => !$this->isPaymentProfileLimitReached(Auth::getUser()),
            'autoRedirectDelayInMs' => 5000,
            'cards' => $this->getCards(Auth::getUser()),
        ]);
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

    protected function getClientId() {
        return $this->isTestMode() ? $this->model->test_client_id : $this->model->live_client_id;
    }

    protected function getClientSecret() {
        return $this->isTestMode() ? $this->model->test_client_secret : $this->model->live_client_secret;
    }

    protected function getTerminalId() {
        return $this->isTestMode() ? $this->model->test_terminal_id : $this->model->live_terminal_id;
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

    protected function handleUpdatePaymentProfile($customer, $data)
    {
        if (!$this->isPaymentProfileLimitReached($customer)) {
            if (!$this->cardExists($customer, $data['merchantTokenId'])) {
                $profile = $this->model->initPaymentProfile($customer);
                $profile->card_brand = $data['cardSchemeName'];
                $profile->card_last4 = substr($data['panStar'], -4);
                $profile->setProfileData($data);
                $profile->save();
            }
        }
    }

    protected function isPaymentProfileLimitReached($customer)
    {
        if (!$customer)
            return true;
        return Payment_profiles_model::where('customer_id', $customer->customer_id)
                ->where('payment_id', $this->model->payment_id)
                ->count() >= 4;
    }

    protected function getCards($customer) {
        if (!$customer)
            return [];
        $cards = [];
        $paymentProfiles = Payment_profiles_model::where('customer_id', $customer->customer_id)
            ->where('payment_id', $this->model->payment_id)->get();

        foreach ($paymentProfiles as $profile) {
            $cards[] = [
                'merchantTokenId' => $profile->profile_data['merchantTokenId'],
                'cardName' => $profile->profile_data['cardName'],
                'panStar' => $profile->profile_data['panStar'],
                'cardSchemeId' => $profile->profile_data['cardSchemeId'],
                'expiryDate' => $profile->profile_data['expiryDate'],
                'isCSCRequired' => false,
                'useStoredBillingData' => true,
            ];
        }
        return $cards;
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
