<?php
/**
 * themes/default/config.php
 * ---------------------------------------------------------
 * Metadata for the "default" theme (the original, fully-featured
 * real-estate landing page). Detailed per-field validation still
 * lives in renderer.php (it's tightly coupled to this theme's
 * exact field set) — this file is the theme's self-description,
 * used by the core dispatcher for identity/listing purposes and
 * by any future admin tooling.
 * ---------------------------------------------------------
 */

return [
    'id'          => 'default',
    'name'        => 'Signature Real Estate',
    'description' => 'The original, full-featured real-estate landing page: hero slider, pricing table, floor plans, gallery, amenities, location map, about-builder, and full CRM/analytics wiring.',
    'requiredFields' => [
        'projectName', 'address', 'phone', 'toEmail', 'priceRange', 'slider',
    ],
];
