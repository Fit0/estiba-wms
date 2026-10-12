<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Architecture rules, enforced by PHPStan through PHPat (see phpstan.dist.neon).
 *
 * They run with `make stan` and in CI. The reasoning is recorded in
 * docs/adr/0001-modular-monolith-with-hexagonal-bounded-contexts.md and
 * docs/adr/0003-cross-context-communication-through-published-contracts.md.
 */
final class ArchitectureTest
{
    private const array CONTEXTS = ['Catalog', 'Warehouse', 'Inventory', 'Inbound', 'Outbound'];

    /** Shared is not a bounded context, but it follows the same layering rules. */
    private const array LAYERED = [...self::CONTEXTS, 'Shared'];

    private const array FRAMEWORKS = ['Symfony', 'Doctrine', 'ApiPlatform', 'Lexik', 'Redis', 'Twig'];

    /**
     * @return iterable<string, Rule>
     */
    public function testDomainIsFrameworkFree(): iterable
    {
        foreach (self::LAYERED as $context) {
            yield "{$context} domain depends on no framework" => PHPat::rule()
                ->classes($this->layer($context, 'Domain'))
                ->shouldNotDependOn()
                ->classes(...array_map($this->namespace(...), self::FRAMEWORKS))
                ->because('The domain model is plain PHP, so it can be tested without a framework or a database.');
        }
    }

    /**
     * @return iterable<string, Rule>
     */
    public function testLayersPointInwards(): iterable
    {
        foreach (self::LAYERED as $context) {
            yield "{$context} domain does not depend on outer layers" => PHPat::rule()
                ->classes($this->layer($context, 'Domain'))
                ->shouldNotDependOn()
                ->classes($this->layer($context, 'Application'), $this->layer($context, 'Infrastructure'))
                ->because('Dependencies point inwards: Infrastructure, then Application, then Domain.');

            yield "{$context} application does not depend on infrastructure or web and persistence frameworks" => PHPat::rule()
                ->classes($this->layer($context, 'Application'))
                ->shouldNotDependOn()
                ->classes(
                    $this->layer($context, 'Infrastructure'),
                    $this->namespace('Doctrine'),
                    $this->namespace('ApiPlatform'),
                    $this->namespace('Symfony\Component\HttpFoundation'),
                    $this->namespace('Symfony\Component\HttpKernel'),
                )
                ->because('Use cases know nothing about HTTP or persistence. Adapters in Infrastructure translate for them.');
        }
    }

    /**
     * @return iterable<string, Rule>
     */
    public function testContextsOnlyMeetThroughPublishedContracts(): iterable
    {
        foreach (self::CONTEXTS as $context) {
            foreach (self::CONTEXTS as $other) {
                if ($context === $other) {
                    continue;
                }

                yield "{$context} reaches {$other} only through its contract" => PHPat::rule()
                    ->classes($this->namespace("App\\{$context}"))
                    ->shouldNotDependOn()
                    ->classes($this->namespace("App\\{$other}"))
                    ->excluding($this->namespace("App\\{$other}\\Application\\Contract"))
                    ->because("Only {$other}'s Application\\Contract is public. See ADR 0003.");
            }

            yield "Shared does not depend on {$context}" => PHPat::rule()
                ->classes($this->namespace('App\Shared'))
                ->shouldNotDependOn()
                ->classes($this->namespace("App\\{$context}"))
                ->because('Contexts depend on Shared, never the other way round.');
        }
    }

    /**
     * @return iterable<string, Rule>
     */
    public function testControllersAreSingleActionServices(): iterable
    {
        $reason = 'A controller does one thing: it turns an HTTP request into a command or a query.';

        yield 'controllers are final' => PHPat::rule()
            ->classes($this->controllers())
            ->shouldBeFinal()
            ->because($reason);

        yield 'controllers are readonly' => PHPat::rule()
            ->classes($this->controllers())
            ->shouldBeReadonly()
            ->because($reason);

        yield 'controllers are invokable' => PHPat::rule()
            ->classes($this->controllers())
            ->shouldBeInvokable()
            ->because($reason);
    }

    private function controllers(): SelectorInterface
    {
        return Selector::classname('/^App\\\\\w+\\\\Infrastructure\\\\Http\\\\\w+Controller$/', true);
    }

    private function layer(string $context, string $layer): SelectorInterface
    {
        return $this->namespace("App\\{$context}\\{$layer}");
    }

    /**
     * @param non-empty-string $namespace
     */
    private function namespace(string $namespace): SelectorInterface
    {
        return Selector::inNamespace($namespace);
    }
}
