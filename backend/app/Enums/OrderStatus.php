<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case PROCESSING = 'PROCESSING';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case DISPUTED = 'DISPUTED';
    case CANCELED = 'CANCELED';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => in_array($next, [self::PAID, self::CANCELLED], true),
            self::PAID => in_array($next, [self::PROCESSING, self::CANCELLED], true),
            self::PROCESSING => in_array($next, [self::SHIPPED, self::CANCELLED], true),
            self::SHIPPED => $next === self::DELIVERED,
            self::DELIVERED => false,
            self::CANCELLED, self::CANCELED => false,
            self::DISPUTED => self::DELIVERED === $next || self::CANCELED === $next,
        };
    }
}
