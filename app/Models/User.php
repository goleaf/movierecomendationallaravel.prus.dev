<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kirschbaum\Commentions\Contracts\Commenter;

class User extends Authenticatable implements Commenter, HasAvatar, HasName
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'cep',
        'street',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return Collection<int, static>
     */
    public static function mentionableForComments(): Collection
    {
        return static::query()
            ->orderBy('name')
            ->get();
    }

    public function getFilamentName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return $this->email;
    }

    public function getCommenterName(): string
    {
        return $this->getFilamentName();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->email === null || $this->email === '') {
            return null;
        }

        $hash = md5(Str::lower(trim($this->email)));

        return sprintf('https://www.gravatar.com/avatar/%s?s=200&d=identicon', $hash);
    }
}
