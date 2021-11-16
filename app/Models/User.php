<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */

    // const CREATED_AT = 'Usetdate';
    // const DELETED_AT = 'DEL';

    protected $table = 'sys_users';
    protected $primaryKey = 'ID';
    public $timestamps = false;


    public function getEmailAttribute()
    {
        return $this->Uemail;
    }

    public function getAuthPassword()
    {
        return $this->Upwd;
    }


    protected $guarded = [];
    // protected $fillable = [
    //     'Utitle',
    //     'Uname',
    //     'Uemail',
    //     'Upwd',
    //     'Urole',
    //     'UID',
    //     'Ustatus',
    //     'SID',
    //     'Uflag',
    // ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'Upwd',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    // ];

    /**
     * 使用者與出題記錄的關聯
     */
    public function csAiQuesRecords()
    {
        return $this->hasMany(CsAiQuesRecord::class, 'user_id', 'ID');
    }

    // /**
    //  *  使用者與題目類型的關聯
    //  */
    public function userScore()
    {
        return $this->hasOne(UserScore::class, 'user_id', 'ID');
    }

    // /**
    //  * 使用者與上課資料的關聯
    //  */
    // public function csClassData()
    // {
    //     return $this->hasMany(CsClassData::class, 'stdId', 'UID');
    // }

    // /**
    //  * 使用者與線上客服的關聯
    //  */
    // public function csService()
    // {
    //     return $this->hasMany(CsService::class, 'setuid', 'UID');
    // }

    // /**
    //  * 使用者與問答家教的關聯
    //  */
    // public function csClassHelp()
    // {
    //     return $this->hasMany(CsClassHelp::class, 'setuid', 'UID');
    // }


    // public function groupTest()
    // {
    //     return $this->belongsToMany(GroupTest::class, 'group_user', 'user_id', 'group_id')->withTimestamps();
    // }
}
