<?php
/**
 * themes/riverside/config.php
 * See themes/default/config.php for the contract this follows.
 */

return [
    'id'          => 'riverside',
    'name'        => 'Riverside Residences',
    'description' => 'A content-rich, image-led luxury residences microsite: full-bleed hero carousel with an inline enquiry form, numbered highlights strip, masonry amenities grid, price cards, floor-plan & master-plan showcases, gallery lightbox, virtual tour embed, location/connectivity panel, about-the-developer, and lead capture wired to four separate forms (hero, contact section, enquiry modal, on-load popup) each with its own spam-check.',
    'requiredFields' => [
        'projectName', 'locationTagline', 'phone', 'toEmail', 'priceText', 'heroImage',
    ],
];
