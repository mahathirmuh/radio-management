<?php

// The names of the checks of the register (RadioCheckService::ISSUES), by their code.

return [
    'needs_review' => 'Needs checking',
    'sn_duplicate' => 'Duplicate SN',
    'id_duplicate' => 'Duplicate Radio ID',
    'id_hex_f' => 'Radio ID contains F',
    'sn_empty' => 'SN empty',
    'sn_format' => 'Non-standard SN format',
    'id_empty' => 'Radio ID empty',
    'type_empty' => 'Radio type empty',
    'lost_no_ba' => 'Lost without BA No.',
    'reprogram' => 'Needs reprogramming',
    'verify_empty' => 'Verification date empty',
    'verify_stale' => 'Verified over 12 months ago',
];
