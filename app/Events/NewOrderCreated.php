<?php

// app/Events/NewOrderCreated.php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class NewOrderCreated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order->load('user');
    }

    public function broadcastOn()
    {
        return new Channel('orders');
    }

    public function broadcastWith()
    {
        return ['order' => $this->order->toArray()];
    }

    public function broadcastAs()
    {
        return 'NewOrderCreated';
    }
}


