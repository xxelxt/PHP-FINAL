<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;
    protected $fillable =[
        'products_id',
        'users_id',
        'ratings',
        'content'
    ];
    public function users()
    {
        return $this->belongsTo(User::class,'users_id','id'); // xác định quan hệ 1:1 giữa bảng rate và user
    }
    public function products()
    {
        return $this->belongsTo(Products::class,'products_id');
    }
}
