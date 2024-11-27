<?php

return [
    'super_admin' => [],
    'admin' => [
        'copy_roles_from' => ['developer'],
    ],
    'support' => [
        'copy_roles_from' => ['developer'],
    ],
    'developer' => [
        'copy_roles_from' => ['store_member', 'store_owner'],
    ],
    'store_owner' => [
        'copy_roles_from' => ['store_member'],
        'create' => [
            'api_users',
            'bills',
            'deliveries',
            'notes',
            'payment_methods',
            'shopify_shops',
            'store',
            'subscriptions',
            'users',
        ],
        'update' => [
            'deliveries',
            'notes',
            'payment_methods',
            'shopify_shops',
            'store',
            'users',
        ],
        'delete' => [
            'api_users',
            'notes',
            'payment_methods',
            'shopify_shops',
            'store',
        ],
    ],
    'store_member' => [
        'read' => [
            'addresses',
            'api_users',
            'bills',
            'customers',
            'deliveries',
            'notes',
            'orders',
            'payment_methods',
            'products',
            'prices',
            'store',
            'subscriptions',
            'users',
        ],
        'create' => [
            'addresses',
            'customers',
            'orders',
            'products',
            'prices'
        ],
        'update' => [
            'addresses',
            'customers',
            'orders',
            'products',
            'prices'
        ],
        'delete' => [
            'addresses',
            'customers',
            'products'
        ],
    ],
];
