<?php

return [
    'apiSecret' => env('SHOPIFY_API_SECRET'),
    'apiKey' => env('SHOPIFY_API_KEY'),
    'apiVersion' => '2024-10',
    'carrierServiceName' => 'Velo',

    'scopes' => [
        'read_products',
        'read_customers',
        'read_shipping',
        'write_shipping',
        'read_orders',
        'write_orders',
        'read_inventory',
        'write_inventory',
        'read_fulfillments',
        'write_fulfillments',
        'read_assigned_fulfillment_orders',
        'write_assigned_fulfillment_orders',
        'read_merchant_managed_fulfillment_orders',
        'write_merchant_managed_fulfillment_orders',
        'read_third_party_fulfillment_orders',
        'write_third_party_fulfillment_orders',
        'write_store_credit_account_transactions',
    ],

    'webhooks' => [
        // mandatory webhhoks
        'mandatory' => [
            'customers/data',
            'customers/redact',
            'shop/redact',
        ],

        'topics' => [
            // app functionallity
            'app/uninstalled',

            // 'customers/create',
            // 'customers/delete',
            // 'customers/update',

            // 'products/create',
            // 'products/delete',
            // 'products/update',

            'orders/cancelled',
            'orders/create',
            'orders/delete',
            'orders/updated',
            'orders/fulfilled',

            'locations/update',
        ],
    ],
];
