<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Domain\DomainRuleViolation;

final class TestRuleBroken extends DomainRuleViolation
{
    public static function now(): self
    {
        return new self('shared.test_rule_broken', ['rule' => 'bus-test'], 'A rule of the bus test was broken.');
    }
}
