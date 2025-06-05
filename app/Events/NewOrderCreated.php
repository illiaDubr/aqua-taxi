<?php

// app/Events/NewOrderCreated.php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;


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
        return new PrivateChannel('orders');
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->order->id,
            'status' => $this->order->status,
            'address' => $this->order->address,
            'quantity' => $this->order->quantity,
            'latitude' => $this->order->latitude,
            'longitude' => $this->order->longitude,
            'payment_method' => $this->order->payment_method,
            'user' => [
                'id' => $this->order->user->id,
                'name' => $this->order->user->name,
                // добавь нужные поля
            ],
        ];
    }

    public function broadcastAs()
    {
        return 'NewOrderCreated';
    }
    public function broadcastWhen()
    {
        Log::info('⚡ Событие будет отправлено!');
        return true;
    }
}



