<?php
/**
 * Seed essential settings rows if they are missing.
 * The admin can change values later via Settings; this just ensures the keys exist.
 */

return [
    'id' => '2026_09_03_default_settings',
    'description' => 'Seed default settings rows (company name, colors, SMTP placeholders)',
    'up' => function (mysqli $conn) {
        $defaults = [
            'company_name'       => 'Shipping Company',
            'company_tagline'    => 'Global Logistics Solutions',
            'primary_color'      => '#152E56',
            'secondary_color'    => '#F9BA34',
            'site_title'         => 'Shipping & Logistics',
            'google_maps_api_key'=> '',
            'smtp_host'          => '',
            'smtp_port'          => '587',
            'smtp_username'      => '',
            'smtp_password'      => '',
            'smtp_encryption'    => 'tls',
            'smtp_from_email'    => '',
            'smtp_from_name'     => '',
        ];

        foreach ($defaults as $key => $value) {
            DatabaseAutoMigrate::ensureSetting($conn, $key, $value);
        }
    },
];
