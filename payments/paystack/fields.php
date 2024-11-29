<?php

return [
    'fields' => [
        'setup' => [
            'type' => 'partial',
            'path' => '$/joytekmotion/paystack/payments/paystack/info',
        ],
        'transaction_mode' => [
            'label' => 'lang:joytekmotion.paystack::default.label_transaction_mode',
            'type' => 'radiotoggle',
            'default' => 'test',
            'span' => 'left',
            'options' => [
                'live' => 'lang:joytekmotion.paystack::default.text_live',
                'test' => 'lang:joytekmotion.paystack::default.text_test',
            ],
        ],
        'integration_type' => [
            'label' => 'lang:joytekmotion.paystack::default.label_integration_type',
            'type' => 'radiotoggle',
            'default' => 'popup',
            'span' => 'right',
            'options' => [
                'popup' => 'lang:joytekmotion.paystack::default.text_popup',
                'redirect' => 'lang:joytekmotion.paystack::default.text_redirect',
            ],
        ],
        'test_secret_key' => [
            'label' => 'lang:joytekmotion.paystack::default.label_test_secret_key',
            'type' => 'text',
            'span' => 'right',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
        'live_secret_key' => [
            'label' => 'lang:joytekmotion.paystack::default.label_live_secret_key',
            'type' => 'text',
            'span' => 'right',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[live]',
            ],
        ],
        'order_fee_type' => [
            'label' => 'lang:igniter.payregister::default.label_order_fee_type',
            'type' => 'radiotoggle',
            'span' => 'right',
            'cssClass' => 'flex-width',
            'default' => 1,
            'options' => [
                1 => 'lang:admin::lang.menus.text_fixed_amount',
                2 => 'lang:admin::lang.menus.text_percentage',
            ],
        ],
        'order_fee' => [
            'label' => 'lang:igniter.payregister::default.label_order_fee',
            'type' => 'currency',
            'span' => 'right',
            'cssClass' => 'flex-width',
            'default' => 0,
            'comment' => 'lang:igniter.payregister::default.help_order_fee',
        ],
        'order_total' => [
            'label' => 'lang:igniter.payregister::default.label_order_total',
            'type' => 'currency',
            'span' => 'left',
            'comment' => 'lang:igniter.payregister::default.help_order_total',
        ],
        'order_status' => [
            'label' => 'lang:igniter.payregister::default.label_order_status',
            'type' => 'select',
            'options' => [\Admin\Models\Statuses_model::class, 'getDropdownOptionsForOrder'],
            'span' => 'right',
            'comment' => 'lang:igniter.payregister::default.help_order_status',
        ],
    ],
    'rules' => [
        ['transaction_mode', 'lang:joytekmotion.paystack::default.label_transaction_mode', 'string'],
        ['integration_type', 'lang:joytekmotion.paystack::default.label_integration_type', 'string'],
        ['live_secret_key', 'lang:joytekmotion.paystack::default.label_live_secret_key', 'string'],
        ['test_secret_key', 'lang:joytekmotion.paystack::default.label_test_secret_key', 'string'],
        ['order_fee_type', 'lang:igniter.payregister::default.label_order_fee_type', 'integer'],
        ['order_fee', 'lang:igniter.payregister::default.label_order_fee', 'numeric'],
        ['order_total', 'lang:igniter.payregister::default.label_order_total', 'numeric'],
        ['order_status', 'lang:igniter.payregister::default.label_order_status', 'integer'],
    ],
];
