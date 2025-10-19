<?php

declare(strict_types=1);

namespace App\Support;

function assert_string_non_empty(mixed $v): string {
    if (!is_string($v) || $v==='') throw new \InvalidArgumentException('Expected non-empty string');
    return $v;
}
/**
 * @template T of object
 * @param mixed $v
 * @param class-string<T> $cls
 * @return T
 */
function assert_instanceof(mixed $v,string $cls){ if(!($v instanceof $cls)) throw new \InvalidArgumentException('instance'); return $v; }
function assert_array(mixed $v): array { if(!is_array($v)) throw new \InvalidArgumentException('array'); return $v; }
