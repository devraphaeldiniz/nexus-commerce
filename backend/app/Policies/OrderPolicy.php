<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->role === 'ADMIN' || $order->user_id === $user->id || $order->customer_email === $user->email;
    }

    public function deliver(User $user, Order $order): bool
    {
        return $user->role === 'ADMIN' || $order->items()->where('seller_id', optional($user->sellerProfile)->id)->exists();
    }
}
