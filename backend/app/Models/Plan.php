<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';
    protected $primaryKey = 'plan_id';
    public $timestamps = false;
}
