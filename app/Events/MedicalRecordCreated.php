<?php

namespace App\Events;

use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MedicalRecordCreated implements ShouldQueue
{
    use Dispatchable, SerializesModels;

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
     * The name of the queue the job should be sent to.
     */
    public function viaQueue(): string
    {
        return 'default';
    }
}
