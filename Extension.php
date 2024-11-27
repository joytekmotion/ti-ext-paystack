<?php
namespace Joytekmotion\Paystack;

use System\Classes\BaseExtension;

class Extension extends BaseExtension
{
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
}
