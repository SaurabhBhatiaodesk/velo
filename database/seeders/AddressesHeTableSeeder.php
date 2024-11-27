<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AddressesHeTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('addresses_he')->delete();
        
        \DB::table('addresses_he')->insert(array (
            0 => 
            array (
                'address_id' => 156,
                'city' => 'קרית אונו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:37',
                'id' => 49,
                'state' => 'מחוז תל אביב',
                'street' => 'יצחק רבין',
                'updated_at' => '2024-03-02 21:25:37',
            ),
            1 => 
            array (
                'address_id' => 67,
                'city' => 'גבעתיים',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:42',
                'id' => 73,
                'state' => 'מחוז תל אביב',
                'street' => 'יפה נוף',
                'updated_at' => '2024-03-02 21:25:42',
            ),
            2 => 
            array (
                'address_id' => 305,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:48',
                'id' => 96,
                'state' => 'מחוז תל אביב',
                'street' => 'יגאל אלון',
                'updated_at' => '2024-03-02 21:25:48',
            ),
            3 => 
            array (
                'address_id' => 293,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:48',
                'id' => 97,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 21:25:48',
            ),
            4 => 
            array (
                'address_id' => 97,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:51',
                'id' => 109,
                'state' => 'מחוז תל אביב',
                'street' => 'בן זכאי',
                'updated_at' => '2024-03-02 21:25:51',
            ),
            5 => 
            array (
                'address_id' => 340,
                'city' => 'יהוד',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:52',
                'id' => 114,
                'state' => 'פתח תקווה מחוז המרכז',
                'street' => 'דרך משה דיין',
                'updated_at' => '2024-03-02 21:25:52',
            ),
            6 => 
            array (
                'address_id' => 394,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:57',
                'id' => 134,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-03-02 21:25:57',
            ),
            7 => 
            array (
                'address_id' => 240,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:25:59',
                'id' => 146,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 21:25:59',
            ),
            8 => 
            array (
                'address_id' => 481,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:26:06',
                'id' => 175,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-03-02 21:26:06',
            ),
            9 => 
            array (
                'address_id' => 904,
                'city' => 'ירושלים',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:26:50',
                'id' => 361,
                'state' => 'מחוז ירושלים',
                'street' => 'השלכת',
                'updated_at' => '2024-03-02 21:26:50',
            ),
            10 => 
            array (
                'address_id' => 906,
                'city' => 'קריית ביאליק',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:26:50',
                'id' => 362,
                'state' => 'חיפה מחוז חיפה',
                'street' => 'הערמונים',
                'updated_at' => '2024-03-02 21:26:50',
            ),
            11 => 
            array (
                'address_id' => 1,
                'city' => 'קריית ביאליק',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:26:52',
                'id' => 369,
                'state' => 'חיפה מחוז חיפה',
                'street' => 'נפתלי',
                'updated_at' => '2024-03-02 21:26:52',
            ),
            12 => 
            array (
                'address_id' => 2,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:26:52',
                'id' => 370,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-03-02 21:26:52',
            ),
            13 => 
            array (
                'address_id' => 260,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:02',
                'id' => 412,
                'state' => 'מחוז תל אביב',
                'street' => 'הקישון',
                'updated_at' => '2024-03-02 21:27:02',
            ),
            14 => 
            array (
                'address_id' => 483,
                'city' => 'פתח תקווה',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:05',
                'id' => 425,
                'state' => 'פתח תקווה מחוז המרכז',
                'street' => 'יעל רום',
                'updated_at' => '2024-03-02 21:27:05',
            ),
            15 => 
            array (
                'address_id' => 835,
                'city' => 'קריית ביאליק',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:07',
                'id' => 432,
                'state' => 'חיפה מחוז חיפה',
                'street' => 'הערמונים',
                'updated_at' => '2024-03-02 21:27:07',
            ),
            16 => 
            array (
                'address_id' => 917,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:07',
                'id' => 433,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-03-02 21:27:07',
            ),
            17 => 
            array (
                'address_id' => 1004,
                'city' => 'קריית ביאליק',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:08',
                'id' => 435,
                'state' => 'חיפה מחוז חיפה',
                'street' => 'הערמונים',
                'updated_at' => '2024-03-02 21:27:08',
            ),
            18 => 
            array (
                'address_id' => 1137,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:20',
                'id' => 485,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-03-02 21:27:20',
            ),
            19 => 
            array (
                'address_id' => 5884,
                'city' => 'כפר יונה',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:27:38',
                'id' => 560,
                'state' => 'השרון מחוז המרכז',
                'street' => 'kfar yona',
                'updated_at' => '2024-03-02 21:27:38',
            ),
            20 => 
            array (
                'address_id' => 20937,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:31:26',
                'id' => 1516,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 21:31:26',
            ),
            21 => 
            array (
                'address_id' => 1538,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:31:30',
                'id' => 1533,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 21:31:30',
            ),
            22 => 
            array (
                'address_id' => 30672,
                'city' => 'חולון',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:31:31',
                'id' => 1537,
                'state' => 'מחוז המרכז',
                'street' => 'גלעדי אהרון',
                'updated_at' => '2024-03-02 21:31:31',
            ),
            23 => 
            array (
                'address_id' => 50297,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:32:10',
                'id' => 1700,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 21:32:10',
            ),
            24 => 
            array (
                'address_id' => 50304,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:32:10',
                'id' => 1701,
                'state' => 'מחוז תל אביב',
                'street' => 'דרך מנחם בגין',
                'updated_at' => '2024-03-02 21:32:10',
            ),
            25 => 
            array (
                'address_id' => 137352,
                'city' => 'רעננה',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:38:06',
                'id' => 3238,
                'state' => 'מחוז המרכז',
                'street' => 'שברץ',
                'updated_at' => '2024-03-02 21:38:06',
            ),
            26 => 
            array (
                'address_id' => 137353,
                'city' => 'רעננה',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:38:06',
                'id' => 3239,
                'state' => 'פתח תקווה מחוז המרכז',
                'street' => '',
                'updated_at' => '2024-03-02 21:38:06',
            ),
            27 => 
            array (
                'address_id' => 185129,
                'city' => 'ראשון לציון',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:39:07',
                'id' => 3501,
                'state' => 'מחוז המרכז',
                'street' => 'אושה',
                'updated_at' => '2024-03-02 21:39:07',
            ),
            28 => 
            array (
                'address_id' => 185128,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:39:07',
                'id' => 3502,
                'state' => 'מחוז תל אביב',
                'street' => 'לוינסקי',
                'updated_at' => '2024-03-02 21:39:07',
            ),
            29 => 
            array (
                'address_id' => 192065,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:39:47',
                'id' => 3673,
                'state' => 'מחוז תל אביב',
                'street' => 'אושה',
                'updated_at' => '2024-03-02 21:39:47',
            ),
            30 => 
            array (
                'address_id' => 155240,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:39:59',
                'id' => 3729,
                'state' => 'מחוז תל אביב',
                'street' => 'דיזנגוף',
                'updated_at' => '2024-03-02 21:39:59',
            ),
            31 => 
            array (
                'address_id' => 198274,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 21:45:47',
                'id' => 5249,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 21:45:47',
            ),
            32 => 
            array (
                'address_id' => 308,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:08:05',
                'id' => 6500,
                'state' => 'מחוז תל אביב',
                'street' => 'הקישון',
                'updated_at' => '2024-03-02 22:08:05',
            ),
            33 => 
            array (
                'address_id' => 1925,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:09:12',
                'id' => 6793,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-03-02 22:09:12',
            ),
            34 => 
            array (
                'address_id' => 3419,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:09:25',
                'id' => 6850,
                'state' => 'מחוז תל אביב',
                'street' => 'הלפרין',
                'updated_at' => '2024-03-02 22:09:25',
            ),
            35 => 
            array (
                'address_id' => 3659,
                'city' => 'יבנאל',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:09:32',
                'id' => 6880,
                'state' => 'כנרת מחוז הצפון',
                'street' => 'משמר השלושה יבנ',
                'updated_at' => '2024-03-02 22:09:32',
            ),
            36 => 
            array (
                'address_id' => 3661,
                'city' => 'יבנאל',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:09:32',
                'id' => 6881,
                'state' => 'כנרת מחוז הצפון',
                'street' => 'מיטב  1',
                'updated_at' => '2024-03-02 22:09:32',
            ),
            37 => 
            array (
                'address_id' => 3664,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:09:32',
                'id' => 6882,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 22:09:32',
            ),
            38 => 
            array (
                'address_id' => 3666,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:09:33',
                'id' => 6883,
                'state' => 'מחוז תל אביב',
                'street' => 'השלושה',
                'updated_at' => '2024-03-02 22:09:33',
            ),
            39 => 
            array (
                'address_id' => 198270,
                'city' => 'רמת השרון',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:10:07',
                'id' => 7033,
                'state' => 'מחוז תל אביב',
                'street' => 'אוסישקין',
                'updated_at' => '2024-03-02 22:10:07',
            ),
            40 => 
            array (
                'address_id' => 198271,
                'city' => 'רמת השרון',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:10:08',
                'id' => 7034,
                'state' => 'מחוז תל אביב',
                'street' => 'אוסישקין',
                'updated_at' => '2024-03-02 22:10:08',
            ),
            41 => 
            array (
                'address_id' => 198272,
                'city' => 'רמת השרון',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:10:08',
                'id' => 7035,
                'state' => 'מחוז תל אביב',
                'street' => 'אוסישקין',
                'updated_at' => '2024-03-02 22:10:08',
            ),
            42 => 
            array (
                'address_id' => 17300,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:10:09',
                'id' => 7041,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-03-02 22:10:09',
            ),
            43 => 
            array (
                'address_id' => 198273,
                'city' => 'ראשון לציון',
                'country' => 'ישראל',
                'created_at' => '2024-03-02 22:18:28',
                'id' => 9236,
                'state' => 'מחוז המרכז',
                'street' => 'משה שרת',
                'updated_at' => '2024-03-02 22:18:28',
            ),
            44 => 
            array (
                'address_id' => 96,
                'city' => 'קריית ביאליק',
                'country' => 'ישראל',
                'created_at' => '2024-03-18 11:38:48',
                'id' => 15768,
                'state' => 'חיפה מחוז חיפה',
                'street' => 'נפתלי',
                'updated_at' => '2024-03-18 11:38:48',
            ),
            45 => 
            array (
                'address_id' => 647670,
                'city' => 'גבעתיים',
                'country' => 'ישראל',
                'created_at' => '2024-03-26 13:57:40',
                'id' => 16984,
                'state' => 'מחוז תל אביב',
                'street' => 'כצנלסון',
                'updated_at' => '2024-03-26 13:57:40',
            ),
            46 => 
            array (
                'address_id' => 14100,
                'city' => 'נתניה',
                'country' => 'ישראל',
                'created_at' => '2024-04-15 16:17:20',
                'id' => 21059,
                'state' => 'מחוז המרכז',
                'street' => 'הקדר',
                'updated_at' => '2024-04-15 16:17:20',
            ),
            47 => 
            array (
                'address_id' => 160674,
                'city' => 'New York',
                'country' => 'ארצות הברית',
                'created_at' => '2024-04-15 16:17:21',
                'id' => 21060,
                'state' => 'NY',
                'street' => 'Broadway',
                'updated_at' => '2024-04-15 16:17:21',
            ),
            48 => 
            array (
                'address_id' => 261,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-04-17 01:06:53',
                'id' => 21604,
                'state' => 'מחוז תל אביב',
                'street' => 'אלנבי',
                'updated_at' => '2024-04-17 01:06:53',
            ),
            49 => 
            array (
                'address_id' => 198269,
                'city' => 'New York',
                'country' => 'ארצות הברית',
                'created_at' => '2024-04-17 01:06:54',
                'id' => 21605,
                'state' => 'NY',
                'street' => 'East 85th Street',
                'updated_at' => '2024-04-17 01:06:54',
            ),
            50 => 
            array (
                'address_id' => 551088,
                'city' => 'Hamburg',
                'country' => 'גרמניה',
                'created_at' => '2024-04-17 01:06:55',
                'id' => 21606,
                'state' => 'HH',
                'street' => 'Grotenkamp',
                'updated_at' => '2024-04-17 01:06:55',
            ),
            51 => 
            array (
                'address_id' => 677,
                'city' => 'New York',
                'country' => 'ארצות הברית',
                'created_at' => '2024-05-05 17:30:01',
                'id' => 25025,
                'state' => 'NY',
                'street' => 'East 84th Street',
                'updated_at' => '2024-05-05 17:30:01',
            ),
            52 => 
            array (
                'address_id' => 126,
                'city' => 'פתח תקווה',
                'country' => 'ישראל',
                'created_at' => '2024-07-21 18:18:26',
                'id' => 35946,
                'state' => 'פתח תקווה מחוז המרכז',
                'street' => 'העצמאות',
                'updated_at' => '2024-07-21 18:18:26',
            ),
            53 => 
            array (
                'address_id' => 17272,
                'city' => 'ראשון לציון',
                'country' => 'ישראל',
                'created_at' => '2024-07-21 18:18:27',
                'id' => 35947,
                'state' => 'מחוז המרכז',
                'street' => 'משה שרת',
                'updated_at' => '2024-07-21 18:18:27',
            ),
            54 => 
            array (
                'address_id' => 137284,
                'city' => 'New York',
                'country' => 'ארצות הברית',
                'created_at' => '2024-07-21 18:18:27',
                'id' => 35948,
                'state' => 'NY',
                'street' => 'West 43rd Street',
                'updated_at' => '2024-07-21 18:18:27',
            ),
            55 => 
            array (
                'address_id' => 198275,
                'city' => 'New York',
                'country' => 'ארצות הברית',
                'created_at' => '2024-07-21 18:18:28',
                'id' => 35949,
                'state' => 'NY',
                'street' => 'Mercer Street',
                'updated_at' => '2024-07-21 18:18:28',
            ),
            56 => 
            array (
                'address_id' => 198287,
                'city' => 'New York',
                'country' => 'ארצות הברית',
                'created_at' => '2024-07-21 18:18:28',
                'id' => 35950,
                'state' => 'NY',
                'street' => 'Mercer Street',
                'updated_at' => '2024-07-21 18:18:28',
            ),
            57 => 
            array (
                'address_id' => 393482,
                'city' => 'Vaughan',
                'country' => 'קנדה',
                'created_at' => '2024-07-21 18:18:29',
                'id' => 35951,
                'state' => 'ON',
                'street' => 'North Park Road',
                'updated_at' => '2024-07-21 18:18:29',
            ),
            58 => 
            array (
                'address_id' => 569130,
                'city' => 'Snir',
                'country' => 'ישראל',
                'created_at' => '2024-07-21 18:18:29',
                'id' => 35952,
                'state' => 'מחוז חיפה',
                'street' => 'Snir',
                'updated_at' => '2024-07-21 18:18:29',
            ),
            59 => 
            array (
                'address_id' => 569131,
                'city' => 'הר גילה',
                'country' => 'israel',
                'created_at' => '2024-07-21 18:18:30',
                'id' => 35953,
                'state' => 'Haifa District',
                'street' => 'הזית',
                'updated_at' => '2024-07-21 18:18:30',
            ),
            60 => 
            array (
                'address_id' => 620793,
                'city' => 'קריית ביאליק',
                'country' => 'ישראל',
                'created_at' => '2024-07-21 18:18:30',
                'id' => 35954,
                'state' => 'מחוז חיפה',
                'street' => 'נפתלי',
                'updated_at' => '2024-07-21 18:18:30',
            ),
            61 => 
            array (
                'address_id' => 620795,
                'city' => 'עכו',
                'country' => 'ישראל',
                'created_at' => '2024-07-21 18:18:31',
                'id' => 35955,
                'state' => 'מחוז הצפון',
                'street' => 'נפתלי',
                'updated_at' => '2024-07-21 18:18:31',
            ),
            62 => 
            array (
                'address_id' => 952984,
                'city' => 'אילת',
                'country' => 'ישראל',
                'created_at' => '2024-07-21 18:18:31',
                'id' => 35956,
                'state' => 'מחוז הדרום',
                'street' => 'אלמוגים',
                'updated_at' => '2024-07-21 18:18:31',
            ),
            63 => 
            array (
                'address_id' => 1008546,
                'city' => 'בני ברק',
                'country' => 'ישראל',
                'created_at' => '2024-08-14 18:35:32',
                'id' => 39381,
                'state' => 'מחוז תל אביב',
                'street' => 'מצדה',
                'updated_at' => '2024-08-14 18:35:32',
            ),
            64 => 
            array (
                'address_id' => 1008545,
                'city' => 'תל אביב-יפו',
                'country' => 'ישראל',
                'created_at' => '2024-08-14 18:35:33',
                'id' => 39382,
                'state' => 'מחוז תל אביב',
                'street' => 'מיטב',
                'updated_at' => '2024-08-14 18:35:33',
            ),
        ));
        
        
    }
}