<?php

declare(strict_types=1);

// Baseline T2 dùng để đối chiếu cấu hình bàn giao với core và fixtures.
// Timezone UTC là quy ước sandbox. Core hiện giữ các giá trị tương ứng cố định.
return [
    'valid_cycles' => [21, 28, 35],
    'default_cycle' => 28,
    'reminder_lead_days' => 2,
    'timezone' => 'UTC',
];
