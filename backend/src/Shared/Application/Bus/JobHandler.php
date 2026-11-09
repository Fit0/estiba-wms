<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marks a handler of the job bus (ADR 0048): work that a schedule starts. The bus has no transaction of
 * its own, because a job may do several things that succeed or fail one by one, each through the command
 * bus, which has its own.
 */
interface JobHandler
{
}
