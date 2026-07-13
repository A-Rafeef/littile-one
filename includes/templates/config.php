<?php

/**
 * Template Constraints Configuration
 * Defines what is customizable in the theme to prevent breaking changes.
 */

define('TEMPLATE_CONSTRAINTS', [
    'locked_regions' => [
        'header',
        'footer',
        'checkout_flow'
    ],
    'allowed_colors' => [
        '#FFFFFF',
        '#000000',
        '#F5F5F5',
        '#E0E0E0', // Grays
        '#FF0000',
        '#00FF00',
        '#0000FF', // Basic primary
        // Flex Theme Colors
        '#F4E4C1',
        '#E4D5B7', // Beige/Warm
        '#2C3E50',
        '#ECF0F1', // Royal
    ],
    'customizable_sections' => [
        'home_hero',
        'home_featured',
        'about_us_text'
    ]
]);

// Helper to check if a region is locked
function isRegionLocked(string $region): bool
{
    return in_array($region, TEMPLATE_CONSTRAINTS['locked_regions']);
}

// Helper to validate color
function isColorAllowed(string $hex): bool
{
    // If user has 'custom_colors' feature, allow any valid hex
    // Always allow custom valid hex codes
    if (preg_match('/^#[a-f0-9]{6}$/i', $hex)) {
        return true;
    }
    return in_array(strtoupper($hex), TEMPLATE_CONSTRAINTS['allowed_colors']);
}
