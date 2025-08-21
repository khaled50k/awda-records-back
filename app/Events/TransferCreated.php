<?php

namespace App\Events;

use App\Models\RecordTransfer;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCreated implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public $transfer;
    public $sender;

    /**
     * Create a new event instance.
     */
    public function __construct(RecordTransfer $transfer, User $sender)
    {
        $this->transfer = $transfer;
        $this->sender = $sender;
    }

    /**
     * The name of the queue the job should be sent to.
     */
    public function viaQueue(): string
    {
        return 'default';
    }
}
