<?php

namespace App\Models;

use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\Polymorphs\Notable;
use App\Traits\Polymorphs\Addressable;
use Illuminate\Support\Facades\Cache;
use Firebase\JWT\JWT;
use Carbon\Carbon;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasRoles, Notable, Addressable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'token',
        'token_created_at',
        'email_verified_at',
        'image',
        'locale_id',
    ];

    /*
     *
     * Is the user elevated?
     *
     */
    public function isElevated()
    {
        return $this->hasRole(['super_admin', 'admin', 'developer', 'support']);
    }


    /**
     * Encrypt the user's password
     *
     */
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    /**
     * Check if the user verified their email address
     *
     * @return boolean
     */
    public function isVerified()
    {
        return ($this->email_verified_at !== null);
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'token',
        'token_created_at',
    ];

    /**
     * The pivot table attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Remove pivot columns from users relationship if exists
        if (isset($array['stores'])) {
            $array['stores'] = array_map(function ($store) {
                unset($store['pivot']['user_id'], $store['pivot']['store_slug']);
                return $store;
            }, $array['stores']);
        }

        return $array;
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'token_created_at' => 'datetime',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    private function base64UrlEncode($text)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn() => str_replace('.', '--DOT--', str_replace('+', '--PLUS--', $this->email)),
        );
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->first_name . ' ' . $this->last_name,
        );
    }

    public function getZendeskToken($store_slug = false)
    {
        $token = Cache::get('velo.user.' . $this->id . '._zendesk_jwt');
        $token = false;
        if (!$token || !strlen($token)) {
            $iat = Carbon::now();
            $headers = ['kid' => config('services.zendesk.key')];
            $payload = [
                'scope' => 'appUser',
                'external_id' => $this->slug,
                'name' => $this->full_name,
                'email' => $this->email,
            ];
            if ($store_slug) {
                $payload['external_id'] = $store_slug;
            }

            //$payload = ['scope' => 'appUser', 'external_id' => 'noom', 'name' => 'ruimi', 'email' => 'noam@noomofficial.com'];
            //$payload = ['scope' => 'appUser', 'external_id' => 'velo-qa', 'name' => 'oryan', 'email' => 'oryan@veloapp.io'];

            $token = JWT::encode($payload, config('services.zendesk.secret'), 'HS256', null, $headers);

            Cache::put('velo.user.' . $this->id . '._zendesk_jwt', $token, $iat->addMinutes(60));
        }

        return $token;
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }

    public function rules()
    {
        $uniqueForId = 'unique:users' . ((!$this->id) ? '' : (',id,' . $this->id));
        return [
            'email' => 'string|email|max:100|' . $uniqueForId,
            'password' => 'string|min:8|max:255',
            'phone' => 'string|regex:/^\+?[0-9]{1,20}$/|' . $uniqueForId, // starts with + numeric with up to 20 characters
        ];
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function team_stores()
    {
        return $this->belongsToMany(Store::class, 'store_user', 'user_id', 'store_slug')
            ->withPivot('invited_at', 'joined_at', 'address_id', 'token', 'store_role');
    }

    public function payment_methods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
