<?php

namespace App\Models;

use AnisAronno\MediaGallery\Traits\HasMedia;
use AnisAronno\MediaGallery\Traits\HasOwnedMedia;
use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Helpers\UniqueSlug;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Ramsey\Uuid\Uuid;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasMedia;
    use HasOwnedMedia;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'email', 'username', 'password', 'phone', 'api_token', 'status', 'gender', 'time_zone', 'language', 'isDeletable', 'asabri_member_number', 'tni_rank', 'tni_unit', 'tni_id_number', 'enrollment_date', 'is_verified'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = ['password', 'remember_token', 'api_token'];

    /**
     * The method that should be cast.
     *
     * @return array
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'status'            => UserStatus::class,
            'gender'            => UserGender::class,
            'enrollment_date'   => 'date',
            'is_verified'       => 'boolean',
        ];
    }

    protected static $recordEvents = ['deleted', 'created', 'updated'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'password', 'status', 'api_token'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model)
        {
            $model->api_token = Uuid::uuid4()->toString();
            $model->username  = UniqueSlug::generate($model, 'username', $model->name);
        });
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailQueued());
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public static function getpermissionGroups()
    {
        $permission_groups = DB::table('permissions')
            ->select('group_name as name')
            ->groupBy('group_name')
            ->get();

        return $permission_groups;
    }

    public static function getpermissionsByGroupName($group_name)
    {
        $permissions = DB::table('permissions')
            ->select('name', 'id')
            ->where('group_name', $group_name)
            ->get();

        return $permissions;
    }

    public static function roleHasPermissions($role, $permissions)
    {
        $hasPermission = true;

        foreach ($permissions as $permission) {
            if (! $role->hasPermissionTo($permission->name)) {
                $hasPermission = false;

                return $hasPermission;
            }
        }

        return $hasPermission;
    }

    public function hasAdministrativeRole(): bool
    {
        return $this->hasRole(['superadmin', 'admin']);
    }

    protected $appends = ['avatar'];

    public function getAvatarAttribute(): string
    {
        return $this->image[0]?->url ?? 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email)));
    }

    // Relationships
    public function insurancePolicies()
    {
        return $this->hasMany(InsurancePolicy::class);
    }

    public function claimRequests()
    {
        return $this->hasMany(ClaimRequest::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function callSchedules()
    {
        return $this->hasMany(CallSchedule::class);
    }

    public function transactionHistories()
    {
        return $this->hasMany(TransactionHistory::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    // Admin-specific relationships
    public function escalatedConversations()
    {
        return $this->hasMany(AiConversation::class, 'escalated_to_admin_id');
    }

    public function scheduledCalls()
    {
        return $this->hasMany(CallSchedule::class, 'admin_id');
    }
}
