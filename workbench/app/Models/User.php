<?php

namespace App\Models;

use App\Enums\Fruit;
use App\Enums\Status;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $timestamps = false;

    public $filtrable = [
        'id',
        'name',
        'posts',
    ];

    public $sortable = [
        'id',
        'name',
    ];

    public $scopable = [
        'foo',
        'bool',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'has_consumer_ability' => 'boolean',
        'first_name' => AsStringable::class,
        'status' => Status::class,
        'favorite_fruits' => AsEnumCollection::class.':'.Fruit::class,
    ];

    public array $primaryIdentifiers = [
        'name',
        'first_name',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'owner_id');
    }

    #[\Deprecated]
    public function publicPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'owner_id')->withAttributes(['name' => 'public']);
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'from_id', 'to_id');
    }

    #[\Deprecated]
    public function amis(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function purchases(): MorphMany
    {
        return $this->morphMany(Purchase::class, 'buyer');
    }

    public function childrenPosts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class, 'parent_id', 'owner_id');
    }

    public function scopeValidated($query)
    {
        $query->where('name', 'validated');
    }

    #[Scope]
    public function age($query, int $age)
    {
        $query->where('age', $age);
    }

    public function scopeBool($query, bool $bool)
    {
        $query->where('has_consumer_ability', $bool);
    }

    public function scopeCarbon($query, ?Carbon $dateTime = null)
    {
        $query->where('email_verified_at', $dateTime);
    }

    public function scopeDateTime($query, DateTime $dateTime)
    {
        $query->where('email_verified_at', $dateTime);
    }

    public function scopeFoo(Builder $query, string $foo, float $bar, Fruit $fruit)
    {
        $query->where('comment', "$foo-$bar-{$fruit->value}");
    }

    public function scopeNotUsable($query, $notTyped)
    {
        $query->where('comment', $notTyped);
    }

    public function scopeResolvable($query, object $resolvableParam)
    {
        $query->where('comment', $resolvableParam);
    }

    public function scopeParamNotResolvable($query, object $object)
    {
        $query->where('comment', $object);
    }
}
