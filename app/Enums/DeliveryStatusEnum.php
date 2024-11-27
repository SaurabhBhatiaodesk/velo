<?php

namespace App\Enums;

enum DeliveryStatusEnum: string
{
    // before transmit
    case Placed = 'placed';
    case Updated = 'updated';
    case AcceptFailed = 'accept_failed';

    // before pickup
    case PendingAccept = 'pending_accept';
    case Accepted = 'accepted';
    case PendingPickup = 'pending_pickup';

    // in transit
    case Transit = 'transit';
    case TransitToDestination = 'transit_to_destination';
    case TransitToWarehouse = 'transit_to_warehouse';
    case TransitToSender = 'transit_to_sender';
    case InWarehouse = 'in_warehouse';

    // cancellations
    case PendingCancel = 'pending_cancel';
    case ServiceCancel = 'service_cancel';
    case DataProblem = 'data_problem';

    // done
    case Cancelled = 'cancelled';
    case Delivered = 'delivered';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
    case Failed = 'failed';
}

/*
משלוחים עם הסטטוסים האלה יחוייבו:
Accepted
PendingPickup
Transit
TransitToDestination
TransitToWarehouse
TransitToSender
InWarehouse
Delivered

משלוחים עם הסטטוסים האלה לא:
Placed
Updated
PendingAccept
Rejected
Cancelled
Refunded
Failed
*/
