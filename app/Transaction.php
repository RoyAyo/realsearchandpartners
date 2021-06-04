<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'user_pay_id', 'amount', 'status'
    ];

    public function user(){
        return $this->belongsTo("App\User",'user_id');
    }

    public function user_paid(){
        return $this->belongsTo("App\User",'user_pay_id');
    }
}
