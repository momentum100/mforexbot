<?php

namespace App\Constants;

/**
 * Funnel stages stored in users.status.
 *
 * The set is closed — these three values are the only ones written to the
 * column. Transitions are one-way (new → registered → deposited) and are
 * applied by:
 *   - ApiController::applyStatusTransition (reg/ftd postbacks)
 *   - Database::set_user_registered in the bot (main menu entry)
 *
 * The string values must match the SQL literals embedded in those call
 * sites; changing them here requires a data migration.
 */
final class UserStatus
{
    public const NEW = 'new';
    public const REGISTERED = 'registered';
    public const DEPOSITED = 'deposited';
}
