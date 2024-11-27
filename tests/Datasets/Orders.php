<?php

dataset('orders', [
  [
    [
      'customer' => [
        'id' => 122,
        'first_name' => 'Mor',
        'last_name' => 'Geffen',
        'email' => '',
        'phone' => '0545209919',
        'store_id' => 1,
        'created_at' => '2023-04-18T13:37:54.000000Z',
        'updated_at' => '2023-04-18T13:37:54.000000Z',
        'shopify_id' => null
      ],
      'customerAddress' => [
        'id' => 240,
        'first_name' => 'Mor',
        'last_name' => 'Geffen',
        'line1' => '5 Meitav St',
        'line2' => 'קומה 13',
        'city' => 'Tel Aviv-Yafo',
        'zipcode' => '',
        'state' => 'Tel Aviv District',
        'country' => 'Israel',
        'phone' => '0545209919',
        'longitude' => '34.7948653',
        'latitude' => '32.0653686',
        'addressable_type' => 'App\Models\Customer',
        'addressable_id' => 122,
        'created_at' => '2023-04-18T13:38:51.000000Z',
        'updated_at' => '2023-04-18T13:38:51.000000Z',
        'slug' => null,
        'translation_of' => null,
        'locale_id' => 1,
        'note' => null
      ],
      'store' => [
        'id' => 1,
        'slug' => 'VELO',
        'name' => 'Velo',
        'first_name' => 'Itay',
        'last_name' => 'Rijensky',
        'phone' => '0545445412',
        'website' => 'https://veloapp.io',
        'timezone' => 'Asia/Jerusalem',
        'week_starts_at' => 7,
        'weekly_deliveries_schedule' => [
          [
            'hours' => '12:30',
            'active' => '',
          ],
          [
            'hours' => '12:30',
            'active' => '',
          ],
          [
            'hours' => '12:30',
            'active' => '',
          ],
          [
            'hours' => '12:30',
            'active' => '',
          ],
          [
            'hours' => '00:00',
            'active' => '',
          ],
          [
            'hours' => '00:00',
            'active' => '',
          ],
          [
            'hours' => '12:30',
            'active' => '',
          ]
        ],
        'currency_id' => 2,
        'user_id' => 1,
        'created_at' => '2023-02-22T19:34:00.000000Z',
        'updated_at' => '2023-04-18T20:20:25.000000Z',
        'always_show_next_day_options' => 1,
        'validate_inventory' => 0,
        'validate_weight' => 0,
        'courier_id' => null
      ],
      'storeAddress' => [
        'id' => 13,
        'first_name' => 'Itay',
        'last_name' => 'Rijensky',
        'line1' => '1 Allenby St',
        'line2' => null,
        'city' => 'Tel Aviv-Yafo',
        'zipcode' => null,
        'state' => 'Tel Aviv District',
        'country' => 'Israel',
        'phone' => '0545445412',
        'longitude' => '34.7655444',
        'latitude' => '32.073768',
        'addressable_type' => 'App\Models\Store',
        'addressable_id' => 1,
        'created_at' => '2023-02-26T15:25:52.000000Z',
        'updated_at' => '2023-03-17T02:49:55.000000Z',
        'slug' => null,
        'translation_of' => null,
        'locale_id' => 1
      ],
      'weight' => 0,
      'products' => [
        [
          'quantity' => 1,
          'name' => 'Try',
          'code' => 'ZE11',
          'price' => 2388,
          'currency_id' => 2,
        ]
      ],
      'polygon_id' => null,
      'dimensions' => [
        'width' => 0,
        'height' => 0,
        'depth' => 0
      ]
    ]
  ]
]);
