<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class LoanCustomer extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;
    use SoftDeletes;

    protected $connection = 'mysql_loan';
    protected $table = 'loan_customers';

    protected $fillable = [
        'main_contact_id',
        'customer_code',
        'business_location_id',
        'business_location_name_snapshot',
        'name',
        'khmer_name',
        'username',
        'password',
        'phone',
        'alternate_phone',
        'login_phone',
        'email',
        'telegram',
        'facebook',
        'gender',
        'date_of_birth',
        'id_card_number',
        'passport_number',
        'address',
        'province',
        'district',
        'commune',
        'village',
        'latitude',
        'longitude',
        'family_contact_name',
        'family_contact_phone',
        'spouse_name',
        'spouse_phone',
        'workplace',
        'monthly_income',
        'customer_type',
        'customer_photo_file_id',
        'id_front_file_id',
        'id_back_file_id',
        'blacklist_status',
        'blacklist_reason',
        'blacklist_date',
        'blacklist_by',
        'note',
        'can_login',
        'last_login_at',
        'allow_gps_tracking',
        'gps_tracking_started_at',
        'gps_tracking_stopped_at',
        'gps_tracking_note',
        'created_by',
        'created_by_name_snapshot',
        'synced_at',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'can_login' => 'boolean',
        'allow_gps_tracking' => 'boolean',
        'last_login_at' => 'datetime',
        'gps_tracking_started_at' => 'datetime',
        'gps_tracking_stopped_at' => 'datetime',
        'blacklist_status' => 'boolean',
        'blacklist_date' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function setPasswordAttribute($value): void
    {
        if (! empty($value)) {
            $this->attributes['password'] = Hash::needsRehash((string) $value) ? Hash::make((string) $value) : $value;
        }
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class, 'customer_id');
    }

    public function locations()
    {
        return $this->hasMany(LoanCustomerRealtimeLocation::class, 'customer_id');
    }

    public function latestLocation()
    {
        return $this->hasOne(LoanCustomerLatestLocation::class, 'customer_id');
    }

    public function chats()
    {
        return $this->hasMany(LoanChatThread::class, 'customer_id');
    }

    public function files()
    {
        return $this->hasMany(LoanFile::class, 'fileable_id')->where('fileable_type', self::class);
    }

    public function createdByUltimateUser()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function chatThreads()
    {
        return $this->hasMany(LoanChatThread::class, 'customer_id');
    }

    public function chatParticipants()
    {
        return $this->hasMany(LoanChatParticipant::class, 'participant_id')->where('participant_type', 'customer');
    }
}
