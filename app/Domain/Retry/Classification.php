<?php declare(strict_types=1);

namespace App\Domain\Retry;

enum Classification: string
{
    case Transient = 'transient';
    case Terminal = 'terminal';
}
