<?php

namespace App\Constants;

/**
 * event_type values accepted by the /postback endpoint.
 *
 * The partner auto-appends these via the {status} macro; see
 * docs/postbacks.md for the mapping. Only REG and FTD drive user.status
 * transitions — the others (REDEP/COMMISSION/WITHDRAWAL) are logged to
 * postback_events for audit but don't mutate user state.
 */
final class PostbackEvent
{
    public const REG = 'reg';
    public const FTD = 'ftd';
    public const REDEP = 'redep';
    public const COMMISSION = 'commission';
    public const WITHDRAWAL = 'withdrawal';
}
