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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $filtrable = [
        'id',
        'name',
        'posts',
    ];

    public $sortable = [
        'id',
        'name',
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
        return $this->hasMany(Post::class, 'user_id');
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    #[\Deprecated]
    public function amis(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeValidated($query)
    {
        $query->where('validated', true);
    }

    #[Scope]
    public function age($query, int $age)
    {
        $query->where('age', $age);
    }

    public function scopeBool($query, bool $bool)
    {
        $query->where('bool', $bool);
    }

    public function scopeCarbon($query, ?Carbon $dateTime = null)
    {
        $query->where('datetime', $dateTime);
    }

    public function scopeDateTime($query, DateTime $dateTime)
    {
        $query->where('datetime', $dateTime);
    }

    public function scopeFoo(Builder $query, string $foo, float $bar, Fruit $fruit)
    {
        $query->where('foo', $foo);
    }

    public function scopeNotUsable($query, $notTyped)
    {
        $query->where('foo', $notTyped);
    }

    public function scopeResolvable($query, object $resolvableParam)
    {
        $query->where('foo', $resolvableParam);
    }

    public function scopeParamNotResolvable($query, object $object)
    {
        $query->where('foo', $object);
    }
}
