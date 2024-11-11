<?php

return [
    'fields' => [
        'transaction_mode' => [
            'label' => 'lang:foodninjas.paystack::default.label_transaction_mode',
            'type' => 'radiotoggle',
            'default' => 'test',
            'span' => 'left',
            'options' => [
                'live' => 'lang:foodninjas.paystack::default.text_live',
                'test' => 'lang:foodninjas.paystack::default.text_test',
            ],
        ],
        'transaction_type' => [
            'label' => 'lang:foodninjas.paystack::default.label_transaction_type',
            'type' => 'radiotoggle',
            'default' => 'SALE',
            'span' => 'right',
            'options' => [
                'AUTH' => 'lang:foodninjas.paystack::default.text_auth_only',
                'SALE' => 'lang:foodninjas.paystack::default.text_auth_settlement',
            ],
        ],
        'test_client_id' => [
            'label' => 'lang:foodninjas.paystack::default.label_test_client_id',
            'type' => 'text',
            'span' => 'left',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
        'test_client_secret' => [
            'label' => 'lang:foodninjas.paystack::default.label_test_client_secret',
            'type' => 'text',
            'span' => 'right',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
        'test_terminal_id' => [
            'label' => 'lang:foodninjas.paystack::default.label_test_terminal_id',
            'type' => 'text',
            'span' => 'left',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
        'live_client_id' => [
            'label' => 'lang:foodninjas.paystack::default.label_live_client_id',
            'type' => 'text',
            'span' => 'left',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[live]',
            ],
        ],
        'live_client_secret' => [
            'label' => 'lang:foodninjas.paystack::default.label_live_client_secret',
            'type' => 'text',
            'span' => 'right',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[live]',
            ],
        ],
        'live_terminal_id' => [
            'label' => 'lang:foodninjas.paystack::default.label_live_terminal_id',
            'type' => 'text',
            'span' => 'left',
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
        ['transaction_mode', 'lang:foodninjas.paystack::default.label_transaction_mode', 'string'],
        ['live_client_id', 'lang:foodninjas.paystack::default.label_live_client_id', 'string'],
        ['live_client_secret', 'lang:foodninjas.paystack::default.label_live_client_secret', 'string'],
        ['live_terminal_id', 'lang:foodninjas.paystack::default.label_live_terminal_id', 'string'],
        ['test_client_id', 'lang:foodninjas.paystack::default.label_test_client_id', 'string'],
        ['test_client_secret', 'lang:foodninjas.paystack::default.label_test_client_secret', 'string'],
        ['test_terminal_id', 'lang:foodninjas.paystack::default.label_test_terminal_id', 'string'],
        ['order_fee_type', 'lang:igniter.payregister::default.label_order_fee_type', 'integer'],
        ['order_fee', 'lang:igniter.payregister::default.label_order_fee', 'numeric'],
        ['order_total', 'lang:igniter.payregister::default.label_order_total', 'numeric'],
        ['order_status', 'lang:igniter.payregister::default.label_order_status', 'integer'],
    ],
];
