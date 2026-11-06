<?php

declare(strict_types=1);

namespace App\Outbound\Application\Intake;

use App\Outbound\Application\Contract\IncomingOrder;
use App\Outbound\Application\Contract\IncomingOrderLine;
use App\Outbound\Application\Contract\IncomingOrderPriority;
use App\Outbound\Application\Contract\IntakeResult;
use App\Outbound\Application\Contract\OrderIntake;
use App\Outbound\Application\RegisterOrder\RegisterOrder;
use App\Outbound\Application\RegisterOrder\RegisterOrderLine;
use App\Outbound\Domain\Exception\OrderReferenceAlreadyUsed;
use App\Outbound\Domain\OrderPriority;
use App\Shared\Application\Bus\CommandBus;

/**
 * Answers the {@see OrderIntake} contract with the use case that registers an order, so an order
 * from outside goes through the same checks as one from the API.
 */
final readonly class CommandOrderIntake implements OrderIntake
{
    public function __construct(private CommandBus $commands)
    {
    }

    #[\Override]
    public function register(IncomingOrder $order): IntakeResult
    {
        try {
            $this->commands->dispatch(new RegisterOrder(
                $order->reference,
                $order->warehouseId,
                $order->customer,
                $order->street,
                $order->postalCode,
                $order->city,
                $order->countryCode,
                $this->priorityOf($order->priority),
                $order->cutOffAt,
                array_map(static fn (IncomingOrderLine $line): RegisterOrderLine => new RegisterOrderLine($line->productId, $line->units), $order->lines),
            ));
        } catch (OrderReferenceAlreadyUsed) {
            return IntakeResult::AlreadyRegistered;
        }

        return IntakeResult::Registered;
    }

    private function priorityOf(IncomingOrderPriority $priority): OrderPriority
    {
        return match ($priority) {
            IncomingOrderPriority::Low => OrderPriority::Low,
            IncomingOrderPriority::Normal => OrderPriority::Normal,
            IncomingOrderPriority::High => OrderPriority::High,
            IncomingOrderPriority::Urgent => OrderPriority::Urgent,
        };
    }
}
