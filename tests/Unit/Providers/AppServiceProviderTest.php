<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use App\Support\Session\ReadOnlyAwareDatabaseSessionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Session\DatabaseSessionHandler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use SessionHandlerInterface;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function it_registers_the_database_session_fallback_handler(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => 'session_sqlite',
            'database.connections.session_sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        (new AppServiceProvider($this->app))->register();

        $manager = $this->app->make('session');

        $reflection = new \ReflectionClass($manager);
        $creatorsProperty = $reflection->getProperty('customCreators');
        $creatorsProperty->setAccessible(true);

        /** @var array<string, callable> $creators */
        $creators = $creatorsProperty->getValue($manager);

        $this->assertArrayHasKey('database', $creators);

        $handler = $creators['database']($this->app);

        $this->assertInstanceOf(ReadOnlyAwareDatabaseSessionHandler::class, $handler);
    }

    #[Test]
    public function it_falls_back_to_the_configured_session_handler_on_read_only_errors(): void
    {
        $primary = Mockery::mock(DatabaseSessionHandler::class);
        $primary->shouldReceive('open')->andReturnTrue();
        $primary->shouldReceive('close')->andReturnTrue();
        $primary->shouldReceive('read')->once()->with('session-id')->andReturn('primary-data');
        $primary->shouldReceive('write')->once()->with('session-id', 'data')->andThrow($this->readOnlyException());
        $primary->shouldReceive('destroy')->never();
        $primary->shouldReceive('gc')->once()->with(123)->andThrow($this->readOnlyException());
        $primary->shouldReceive('setExists')->andReturnSelf();

        $fallback = Mockery::mock(SessionHandlerInterface::class);
        $fallback->shouldReceive('open')->andReturnTrue();
        $fallback->shouldReceive('close')->andReturnTrue();
        $fallback->shouldReceive('read')->with('session-id')->andReturn('fallback-data');
        $fallback->shouldReceive('write')->once()->with('session-id', 'data')->andReturnTrue();
        $fallback->shouldReceive('destroy')->once()->with('session-id')->andReturnTrue();
        $fallback->shouldReceive('gc')->once()->with(123)->andReturn(0);

        $handler = new ReadOnlyAwareDatabaseSessionHandler($primary, $fallback);

        $this->assertTrue($handler->open('path', 'name'));
        $this->assertSame('primary-data', $handler->read('session-id'));

        $this->assertTrue($handler->write('session-id', 'data'));
        $this->assertSame('fallback-data', $handler->read('session-id'));

        $this->assertTrue($handler->destroy('session-id'));
        $this->assertSame(0, $handler->gc(123));
        $this->assertTrue($handler->close());
    }

    private function readOnlyException(): QueryException
    {
        return new QueryException('database', 'update "sessions" set "payload" = ?', [], new \PDOException('attempt to write a readonly database'));
    }
}
