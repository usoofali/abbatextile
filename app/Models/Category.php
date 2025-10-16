<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['name', 'description', 'default_unit_type'];

    /**
     * Get the products of this category
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
