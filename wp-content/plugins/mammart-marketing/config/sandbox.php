<?php
declare(strict_types=1);

return [
    'label' => 'SIMULATION ONLY',
    'mode' => 'sandbox',
    'campaign_id' => 'DEMO-SIX-MONTHS-2026',
    'timezone' => 'Asia/Ho_Chi_Minh', // Trial timezone, not a site configuration change.
    'discount_percent' => 15,
    'as_of' => '2026-11-06 09:00:00',
    // No silent bypass. Default checkout result stays PENDING_POLICY.
    'pricing_policy' => 'UNCONFIRMED',
    'simulation_min_eligible_vnd' => null,
    'simulation_cap_vnd' => null,
    'simulation_rounding' => 'UNCONFIRMED',
    'late_registration_policy' => 'UNCONFIRMED',
    'date_boundary_policy' => 'UNCONFIRMED',
    'order_usage_policy' => 'UNCONFIRMED',
];
