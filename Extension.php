<?php
namespace Foodninjas\DnaPayments;

use Admin\Facades\AdminAuth;
use Admin\Models\Orders_model;
use Admin\Models\Payments_model;
use Illuminate\Support\Facades\Event;
use System\Classes\BaseExtension;
use Thoughtco\OrderApprover\Events\OrderCreated;

class Extension extends BaseExtension
{
    public function boot()
    {
        /**
         * Listen to admin.controller.beforeResponse event to dispatch any orders with default DNA payment status
         */
        Event::listen('admin.controller.beforeResponse', function ($controller, $params) {
            if (!AdminAuth::isLogged() or !$controller->getLocationId()) return;

            Payments_model::where([
                'class_name' => 'Foodninjas\DnaPayments\Payments\Paystack',
                'status' => 1,
            ])
                ->each(function ($payment) {
                    if (!$payment->data)
                        return;

                    // dispatch any orders with default dna payment status
                    Orders_model::where([
                        'status_id' => $payment->data['order_status'] ?? null,
                        'payment' => $payment->code,
                    ])
                        ->each(function ($order) {
                            Event::dispatch(new OrderCreated($order));
                        });
                });
        });

        /**
         * Listen to Order Approver events when an order is accepted
         */
        Event::listen('thoughtco.orderApprover.orderAccepted', function ($notifier, $order) {
            $order = Orders_model::with(['payment_logs', 'payment_method'])->find($order->order_id);

            if ($this->isDnaPaymentOrder($order)) {
                $transactionId = $this->getTransactionIdFromOrder($order);

                if (!$transactionId) {
                    return;
                }

                $order->payment_method->settleAuthPayment($transactionId, $order);
            }

        });

        /**
         * Listen to Order Approver events when an order is rejected
         */
        Event::listen('thoughtco.orderApprover.orderRejected', function ($notifier, $order) {
            $order = Orders_model::with(['payment_logs', 'payment_method'])->find($order->order_id);

            if ($this->isDnaPaymentOrder($order)) {
                $transactionId = $this->getTransactionIdFromOrder($order);

                if ($transactionId) {
                    $order->payment_method->cancelAuthPayment($transactionId, $order);
                }
            }

        });
    }

    public function registerPaymentGateways()
    {
        return [
            \Joytekmotion\Paystack\Payments\Paystack::class => [
                'code' => 'paystack',
                'name' => 'lang:joytekmotion.paystack::default.text_payment_title',
                'description' => 'lang:joytekmotion.paystack::default.text_payment_desc'
            ]
        ];
    }

    protected function getTransactionIdFromOrder($order)
    {
        $transactionId = null;
        foreach($order->payment_logs as $paymentLog) {
            $response = $paymentLog->response;
            $isSuccessful = array_get($response, 'success');
            $isSettled = array_get($response, 'settled');
            if ($isSuccessful && !$isSettled) {
                $transactionId = array_get($response, 'id');
            }
        }
        return $transactionId;
    }

    /**
     * Check if the order is a DNA payment order
     * @param $order
     * @return bool
     */
    protected function isDnaPaymentOrder($order)
    {
        return isset($order->payment_method) && $order->payment_method->class_name == 'Foodninjas\DnaPayments\Payments\Paystack';
    }
}
