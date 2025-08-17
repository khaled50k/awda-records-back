<?php

namespace App\Events;

use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MedicalRecordCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $medicalRecord;
    public $creator;

    /**
     * Create a new event instance.
     */
    public function __construct(MedicalRecord $medicalRecord, User $creator)
    {
        $this->medicalRecord = $medicalRecord;
        $this->creator = $creator;
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
            'type' => 'medical_record_created',
            'title' => 'New Medical Record Created',
            'message' => "A new medical record has been created by {$this->creator->full_name}",
            'record_id' => $this->medicalRecord->record_id,
            'patient_name' => $this->medicalRecord->patient->full_name,
            'problem_type' => $this->medicalRecord->problemType->label_en ?? 'Unknown',
            'created_at' => $this->medicalRecord->created_at->format('Y-m-d H:i:s'),
            'creator_name' => $this->creator->full_name,
            'creator_id' => $this->creator->user_id,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'medical.record.created';
    }

    /**
     * The name of the queue the job should be sent to.
     */
    public function viaQueue(): string
    {
        return 'broadcasting';
    }
}
