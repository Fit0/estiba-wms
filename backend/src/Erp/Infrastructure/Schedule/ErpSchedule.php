<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Schedule;

use App\Erp\Application\ImportErpOrders;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * What runs by itself in connection with the ERP (ADR 0048). A worker that consumes `scheduler_erp`
 * sends the messages when their time comes (the `scheduler` service of Compose).
 */
#[AsSchedule('erp')]
final class ErpSchedule implements ScheduleProviderInterface
{
    #[\Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every('30 seconds', new ImportErpOrders()));
    }
}
