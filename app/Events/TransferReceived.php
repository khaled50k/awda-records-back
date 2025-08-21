<?php

namespace App\Events;

use App\Models\RecordTransfer;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferReceived implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public $transfer;
    public $recipient;

    /**
     * Create a new event instance.
     */
    public function __construct(RecordTransfer $transfer, User $recipient)
    {
        $this->transfer = $transfer;
        $this->recipient = $recipient;
    }

    /**
     * The name of the queue the job should be sent to.
     */
    public function viaQueue(): string
    {
        return 'default';
    }
}
