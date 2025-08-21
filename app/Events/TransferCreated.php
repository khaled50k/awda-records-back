<?php

namespace App\Events;

use App\Models\RecordTransfer;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transfer;
    public $sender;
    public $recipient;

    /**
     * Create a new event instance.
     */
    public function __construct(RecordTransfer $transfer, User $sender, User $recipient)
    {
        $this->transfer = $transfer;
        $this->sender = $sender;
        $this->recipient = $recipient;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin.notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'transfer_created',
            'title' => 'New Record Transfer Created',
            'message' => "A new record transfer has been created from {$this->sender->full_name} to {$this->recipient->full_name}",
            'transfer_id' => $this->transfer->transfer_id,
            'record_id' => $this->transfer->record_id,
            'patient_name' => $this->transfer->medicalRecord->patient->full_name ?? 'Unknown',
            'sender_name' => $this->sender->full_name,
            'sender_id' => $this->sender->user_id,
            'recipient_name' => $this->recipient->full_name,
            'recipient_id' => $this->recipient->user_id,
            'transfer_notes' => $this->transfer->transfer_notes,
            'created_at' => $this->transfer->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'transfer.created';
    }

    /**
     * The name of the queue the job should be sent to.
     */
    public function viaQueue(): string
    {
        return 'broadcasting';
    }
}
