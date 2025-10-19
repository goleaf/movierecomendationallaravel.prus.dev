<?php

declare(strict_types=1);

namespace App\Support\Session;

use Illuminate\Database\QueryException;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\ExistenceAwareInterface;
use SessionHandlerInterface;
use Throwable;

class ReadOnlyAwareDatabaseSessionHandler implements ExistenceAwareInterface, SessionHandlerInterface
{
    private bool $usingFallback = false;

    public function __construct(
        private readonly DatabaseSessionHandler $primary,
        private readonly SessionHandlerInterface $fallback,
    ) {}

    public function open(string $savePath, string $sessionName): bool
    {
        $primaryOpened = $this->primary->open($savePath, $sessionName);
        $fallbackOpened = $this->fallback->open($savePath, $sessionName);

        return $primaryOpened && $fallbackOpened;
    }

    public function close(): bool
    {
        $primaryClosed = $this->primary->close();
        $fallbackClosed = $this->fallback->close();

        return $primaryClosed && $fallbackClosed;
    }

    public function read(string $sessionId): string|false
    {
        if ($this->usingFallback) {
            return $this->fallback->read($sessionId);
        }

        return $this->primary->read($sessionId);
    }

    public function write(string $sessionId, string $data): bool
    {
        if ($this->usingFallback) {
            return $this->fallback->write($sessionId, $data);
        }

        try {
            return $this->primary->write($sessionId, $data);
        } catch (Throwable $throwable) {
            if (! $this->shouldFallbackFor($throwable)) {
                throw $throwable;
            }

            $this->activateFallback();

            return $this->fallback->write($sessionId, $data);
        }
    }

    public function destroy(string $sessionId): bool
    {
        if ($this->usingFallback) {
            return $this->fallback->destroy($sessionId);
        }

        try {
            return $this->primary->destroy($sessionId);
        } catch (Throwable $throwable) {
            if (! $this->shouldFallbackFor($throwable)) {
                throw $throwable;
            }

            $this->activateFallback();

            return $this->fallback->destroy($sessionId);
        }
    }

    public function gc(int $lifetime): int|false
    {
        try {
            $primaryResult = $this->primary->gc($lifetime);
        } catch (Throwable $throwable) {
            if (! $this->shouldFallbackFor($throwable)) {
                throw $throwable;
            }

            $this->activateFallback();
            $primaryResult = 0;
        }

        $fallbackResult = $this->fallback->gc($lifetime);

        if ($primaryResult === false || $fallbackResult === false) {
            return false;
        }

        return $primaryResult + $fallbackResult;
    }

    public function setExists($value): SessionHandlerInterface
    {
        if (method_exists($this->primary, 'setExists')) {
            $this->primary->setExists($value);
        }

        if (method_exists($this->fallback, 'setExists')) {
            $this->fallback->setExists($value);
        }

        return $this;
    }

    private function shouldFallbackFor(Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }

        $message = mb_strtolower($throwable->getMessage());

        return str_contains($message, 'readonly') || str_contains($message, 'read-only');
    }

    private function activateFallback(): void
    {
        $this->usingFallback = true;
    }
}
