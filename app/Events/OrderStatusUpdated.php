<?php
// app/Events/OrderStatusUpdated.php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order->load('user', 'driver');
    }

    public function broadcastOn()
    {
        return new Channel('orders'); // общий канал, который слушают водители и юзеры
    }

    public function broadcastWith()
    {
        return [
            'order' => $this->order->toArray(),
        ];
    }

    public function broadcastAs()
    {
        return 'OrderStatusUpdated';
    }
}


