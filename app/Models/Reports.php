<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reports extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reports';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'type',
        'description'
    ];

    /**
     * Get the report type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the report name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the report description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}
