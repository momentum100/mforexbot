"""String constants shared across the bot process.

Mirrors web/app/Constants/{UserStatus,PostbackEvent}.php. Both sides write
and read these exact literal values to the same MySQL columns
(users.status, postback_events.event_type), so the two definitions must
stay in lockstep. Changing a value here requires updating the PHP side
and, for UserStatus, a data migration.
"""


class UserStatus:
    """Funnel stages stored in users.status.

    Transitions are one-way: NEW -> REGISTERED -> DEPOSITED. Applied by:
      - db.Database.set_user_registered (bot upgrades NEW -> REGISTERED
        when the user reaches the main menu)
      - web ApiController::applyStatusTransition (postback-driven upgrades)
    """

    NEW = "new"
    REGISTERED = "registered"
    DEPOSITED = "deposited"


class PostbackEvent:
    """Values the partner can send in the {status} macro.

    Only REG drives bot-side logic today (has_postback_event(..., REG) is
    the affiliate-gate pass check); the rest are logged but not acted on
    from Python.
    """

    REG = "reg"
    FTD = "ftd"
    REDEP = "redep"
    COMMISSION = "commission"
    WITHDRAWAL = "withdrawal"
