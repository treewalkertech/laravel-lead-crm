<?php

return [
    /**
     * Number of days a lead can sit in a single pipeline stage before it's highlighted as
     * overdue on the Kanban board. Configurable via the LEAD_STAGE_ROT_DAYS env var.
     */
    'stage_rot_days' => env('LEAD_STAGE_ROT_DAYS', 10),
];
