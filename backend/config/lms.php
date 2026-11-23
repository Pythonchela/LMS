<?php

/*
| Platform rules in one place.
*/

return [
    // How students log in: 'email' (a one-time code is emailed; Brevo) or 'phone' (the SMS login, kept but switched off).
    // The student app must use the same value (NEXT_PUBLIC_LOGIN_METHOD).
    'login_method' => env('LMS_LOGIN_METHOD', 'email') === 'phone' ? 'phone' : 'email',

    // Devices a student can be logged in on at once. 0 = no limit: a student is only logged out when they log out
    // themselves. With a number (e.g. 2) a login on one more device signs out the oldest one.
    'max_devices' => (int) env('LMS_MAX_DEVICES', 0),

    // Student app URL (used in emails and CORS).
    'student_app_url' => env('STUDENT_APP_URL', 'http://localhost:3000'),

    // Host of the admin panel (empty locally, where it lives at /admin).
    'admin_domain' => env('ADMIN_DOMAIN'),

    // Country code added to 10-digit phone numbers when sending SMS.
    'default_country_code' => '+91',

    // PyMuPDF helper (tools/pdf_tool.py): reads questions out of PDFs and, if pdftoppm is missing, renders pages.
    //   Setup:  pip install pymupdf
    'python' => env('LMS_PYTHON', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
    'pdf_tool' => base_path('tools/pdf_tool.py'),

    // Protected PDFs: poppler's pdftoppm renders pages to images; a TrueType font is used for the watermark.
    'pdftoppm' => env('LMS_PDFTOPPM', 'pdftoppm'),
    'watermark_font' => env('LMS_WATERMARK_FONT'),

    // How long a page-image link stays valid (minutes). Links are signed and tied to one student.
    'pdf_link_minutes' => 15,

    // Link with the WordPress site (mymerchantnavy.com). Both sites hold the same long random secret; every message
    // between them is signed with it. Empty = the link is switched off.
    'wordpress' => [
        'secret' => env('LMS_WP_SECRET'),
        'sso_minutes' => 5,   // how long a "go to my course" sign-in link works (and only once)
    ],

    // Payments (Razorpay, checkout inside the student site). The keys live in .env only. Empty keys = paid checkout is
    // switched off and the batch store falls back to the old purchase link. Use Razorpay TEST keys until you go live.
    'billing' => [
        'razorpay_key_id' => env('RAZORPAY_KEY_ID'),
        'razorpay_key_secret' => env('RAZORPAY_KEY_SECRET'),
        'razorpay_webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'razorpay_api' => env('RAZORPAY_API', 'https://api.razorpay.com/v1'),
        'currency' => 'INR',
        // Prices include GST. When this is above 0 the receipt shows how much of the total is tax.
        'gst_percent' => (float) env('LMS_GST_PERCENT', 0),
        'receipt_prefix' => env('LMS_RECEIPT_PREFIX', 'MMN'),
        // Printed at the top of every receipt.
        'business' => [
            'name' => env('LMS_BUSINESS_NAME', 'My Merchant Navy'),
            'address' => env('LMS_BUSINESS_ADDRESS', ''),
            'gstin' => env('LMS_BUSINESS_GSTIN', ''),
            'email' => env('LMS_BUSINESS_EMAIL', 'support@mymerchantnavy.com'),
            'website' => env('LMS_BUSINESS_WEBSITE', 'https://mymerchantnavy.com'),
        ],
    ],

    // Assignment upload limit (KB).
    'assignment_max_kb' => 20480,
];
