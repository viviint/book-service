<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Book extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'genre',
        'description',
        'cover_image_url',
        'stock_total',
        'stock_available',
        'price_per_day',
        'published_year',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price_per_day'   => 'decimal:2',
        'stock_total'     => 'integer',
        'stock_available' => 'integer',
        'published_year'  => 'integer',
    ];

    /**
     * Scope a query to only include books that are available for borrowing.
     */
    public function scopeAvailable($query)
    {
        return $query->where('stock_available', '>', 0);
    }

    /**
     * Get whether the book is currently available.
     */
    public function getIsAvailableAttribute(): bool
    {
        return (bool) ($this->stock_available > 0);
    }
}
