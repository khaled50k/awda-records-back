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

class TransferReceived implements ShouldBroadcast, ShouldQueue
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
            new PrivateChannel("user.{$this->recipient->user_id}.notifications"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'transfer_received',
            'title' => 'New Medical Record Transfer',
            'message' => "You have received a medical record transfer from {$this->sender->full_name}",
            'transfer_id' => $this->transfer->transfer_id,
            'record_id' => $this->transfer->record_id,
            'patient_name' => $this->transfer->medicalRecord->patient->full_name ?? 'Unknown',
            'sender_name' => $this->sender->full_name,
            'sender_id' => $this->sender->user_id,
            'transfer_notes' => $this->transfer->transfer_notes,
            'received_at' => $this->transfer->created_at->format('Y-m-d H:i:s'),
            'action_required' => true,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'transfer.received';
    }

    /**
     * The name of the queue the job should be sent to.
     */
    public function viaQueue(): string
    {
        return 'broadcasting';
    }
}
