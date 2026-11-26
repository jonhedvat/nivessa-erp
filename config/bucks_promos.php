<?php

// Nivessa Bucks promo days: every sale finalized on one of these dates
// (store time, America/Los_Angeles) earns the reward points times the multiplier.
// Sarah 2026-11-25: 2X Nivessa Bucks on all purchases, Black Friday 11/27/26 only.

return [
    'multiplier_days' => [
        '2026-11-27' => 2,
    ],

    // Gift card bonus days: every gift card sold at the register on one of
    // these dates (store time) gets `bonus` dollars added for every `per`
    // dollars loaded. Sarah 2026-11-26: $5 free with every $50 gift card,
    // Black Friday 11/27/26 only. Automatic, the cashier does nothing extra.
    'gift_card_bonus_days' => [
        '2026-11-27' => ['per' => 50, 'bonus' => 5],
    ],
];
