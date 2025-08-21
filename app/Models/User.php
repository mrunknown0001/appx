<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class User extends Authenticatable implements FilamentUser, HasAvatar, Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'position',
        'contact_number',
        'password',
        'is_admin',
        'role',
        'avatar_url',
        'shift',
        'date_hired',
        'status',
        'last_login_at',
    ];

    /**
     * Attributes to include in the Audit.
     *
     * @var array
     */
    protected $auditInclude = [
        'id',
        'employee_id',
        'name',
        'email',
        'position',
        'contact_number',
        'password',
        'role',
        'avatar_url',
        'shift',
        'date_hired',
        'status',
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

    public function canAccessPanel(Panel $panel): bool
    {
        \Log::info('Checking panel access for user: ' . $this->employee_id);
        return true;
    }

     public function getFilamentAvatarUrl(): ?string
     {
        $avatarColumn = config('filament-edit-profile.avatar_column', 'avatar_url');
        return $this->$avatarColumn ? Storage::url($this->$avatarColumn) : null;
     }

    public function getAuthIdentifierName()
    {
        return 'employee_id';
    }

    public function findForAuth(string $employee_id): ?self
    {
        return static::where('employee_id', $employee_id)->first();
    }

}
