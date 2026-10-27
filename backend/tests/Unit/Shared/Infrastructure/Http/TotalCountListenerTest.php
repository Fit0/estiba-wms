<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use ApiPlatform\State\Pagination\TraversablePaginator;
use App\Shared\Infrastructure\Http\TotalCountListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TotalCountListenerTest extends TestCase
{
    public function testAPagedListSaysHowManyItemsItHasInAllItsPages(): void
    {
        $paginator = new TraversablePaginator(new \ArrayIterator(['a', 'b']), 2, 2, 125);

        $response = $this->handle($paginator);

        self::assertSame('125', $response->headers->get('X-Total-Count'));
    }

    public function testAListWithNothingSaysZero(): void
    {
        $response = $this->handle(new TraversablePaginator(new \ArrayIterator([]), 1, 30, 0));

        self::assertSame('0', $response->headers->get('X-Total-Count'));
    }

    public function testAnAnswerThatIsNotAPagedListHasNoTotal(): void
    {
        self::assertFalse($this->handle(['one item'])->headers->has('X-Total-Count'));
        self::assertFalse($this->handle(null)->headers->has('X-Total-Count'));
    }

    public function testAnErrorHasNoTotal(): void
    {
        $paginator = new TraversablePaginator(new \ArrayIterator([]), 1, 30, 7);

        self::assertFalse($this->handle($paginator, Response::HTTP_BAD_REQUEST)->headers->has('X-Total-Count'));
    }

    public function testASubRequestHasNoTotal(): void
    {
        $paginator = new TraversablePaginator(new \ArrayIterator([]), 1, 30, 7);

        self::assertFalse($this->handle($paginator, Response::HTTP_OK, HttpKernelInterface::SUB_REQUEST)->headers->has('X-Total-Count'));
    }

    private function handle(mixed $data, int $status = Response::HTTP_OK, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $request = Request::create('/api/stock-items');
        $request->attributes->set('data', $data);
        $response = new Response('[]', $status);

        (new TotalCountListener())(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response));

        return $response;
    }
}
