<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

/**
 * Why units that are in the warehouse cannot be promised to a demand (STK-06, STK-12, WH-06).
 */
enum UnreservableReason: string
{
    /** They are at a dock: received but not put away yet. */
    case AtDock = 'at_dock';

    /** They are in a location that is not meant to be picked from: staging, packing or quarantine. */
    case NotPickableLocation = 'not_pickable_location';

    /** Their location is blocked, or no longer exists. */
    case BlockedLocation = 'blocked_location';

    /** They are in another warehouse than the one the demand is served from. */
    case OtherWarehouse = 'other_warehouse';

    /** Their status is not available: quarantine, damaged or on hold. */
    case NotAvailable = 'not_available';

    /** They would reach the customer with less shelf life than the product promises. */
    case ShelfLife = 'shelf_life';

    /** They are in a place where they could be picked, but other demands hold them. */
    case ReservedForOthers = 'reserved_for_others';
}
