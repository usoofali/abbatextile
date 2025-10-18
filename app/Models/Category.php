<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['name', 'description', 'default_unit_type'];

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (empty($category->id)) {
                $category->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the products of this category
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
