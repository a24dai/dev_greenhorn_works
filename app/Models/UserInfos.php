<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\notifications\ResetPasswordNotification;

class UserInfos extends Authenticatable
{
    use SoftDeletes, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'sex',
        'birthday',
        'email',
        'slack_user_id',
        'tel',
        'hire_date',
        'store_id',
        'access_right',
        'position_name',
        'position_code'
    ];

    protected $dates = ['deleted_at'];

    public function store ()
    {
        return $this->belongsTo('App\Models\Stores');
    }

    public function user()
    {
        return $this->hasOne('App\Models\User','user_info_id');
    }

    public function admin()
    {
        return $this->hasOne('App\Models\AdminUsers', 'user_info_id');
    }

    public function scopeWhereName($query, $field, $name)
    {
        if (!$field || !$name) {
            return $query;
        }
        switch($field) {
            case 'first_name':
            case 'last_name':
                return $query->where($field, 'like', '%'.$name.'%');
                break;
            default:
                return $query;
        }
    }

    /**
     * 指定されたDBのカラムを取得。
     * データが空であれば処理なし。
     */
    public function scopeEqual($query, $field, $data)
    {

        // fieldまたはnameに情報が入っていなければ、処理を終了。
        if (!$field || !$data) {
            return $query;
        }

        // fieldの値がemail, tel, sex以外は処理しない。
        switch($field) {
            case 'email':
            case 'tel':
            case 'sex':
                return $query->where($field, $data);
                break;
            default:
                return $query;
        }
    }

    /**
     * データを範囲で指定し、ユーザーインフォ情報を取得。
     * データが空であれば処理なし。
     */
    public function scopeDateRange($query, $field, $start_date, $end_date) {
        if (!$field || (!$start_date && !$end_date)) {
            return $query;
        }

        switch($field) {
            case 'birthday':
            case 'hire_date':
                if ($start_date) {
                    $query->where($field, '>=', date($start_date));
                }
                if ($end_date) {
                    $query->where($field, '<=', date($end_date));
                }
                return $query;
                break;
            default:
                return $query;
        }
    }

    public function scopeFilterByPositionCode($query, $position_code) {
        if(!$position_code) {
            return $query;
        }
        return $query->where('position_code', '<', $position_code);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function saveUserInfo($input)
    {
        $this->create([
            'first_name' => $input['first_name'],
            'last_name' =>$input['last_name'],
            'sex' => $input['sex'],
            'birthday'=>$input['birthday'],
            'email' => $input['email'],
            'tel' => $input['tel'],
            'hire_date' => $input['hire_date'],
            'store_id' => $input['store_id'],
            'access_right' => 0,
            'position_code' => 0
        ]);
    }

    public function updateUserInfo($input, $user)
    {
        $this->where('id',$user['user_info_id'])->update([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'sex' => $input['sex'],
            'birthday' => $input['birthday'],
            'email'=>$input["email"],
            'tel'=>$input['tel'],
            'hire_date'=>$input['hire_date'],
            'store_id'=>$input['store_id'],
            'access_right' => 0,
            'position_code' => 100
         ]);
    }

    public function getUserRecord($email)
    {
        return $this->where('email', $email)->first();
    }

    public function getAdminUserEmail($email)
    {
        return $this->where('email', $email)->first();
    }

    public function getAdminUserId($id)
    {
        return $this->where('id', $id)->first();
    }

    public function getUserList($id)
    {
        return $this->where('store_id', $id)->get();
    }

    public function getAdminUsersByPositionCode($admin_user_info_id)
    {
        $adminuser = $this->when($admin_user_info_id, function($query) use ($admin_user_info_id) {
                         return $query->where('id', $admin_user_info_id);
                     })->first();
        return $this->filterByPositionCode($adminuser['position_code'])->get();
    }

    public function getEmailByUserInfoId($user_info_id)
    {
        return $this->when($user_info_id, function($query) use ($user_info_id) {
            return $query->where('id', $user_info_id);
        })->get();
    }

    public function getUserInfoByAdminUserId($admin_user_id)
    {
        return $this->whereHas('admin', function($query) use ($admin_user_id) {
            return $query->when($admin_user_id, function($query) use ($admin_user_id){
                return $query->where('id', $admin_user_id);
            });
        })->get();
    }

    public function permitAccessRights($admin_user_info_id, $access_rights)
    {
        // 全てのアクセス権限のフラグを組み合わせ、一つの文字列にする 例）011
        $access_right_strbin =  $access_rights['admin_right']
                               .$access_rights['user_right']
                               .$access_rights['store_right'];

        // 文字列から数字へ変換
        $access_right = bindec($access_right_strbin);

        // アクセスを許可
        return $this->where('id', $admin_user_info_id)->update(['access_right' => $access_right]);
    }

    public function getUserInfoByUserId($user_id)
    {
        return $this->whereHas('admin', function($query) use ($user_id) {
            return $query->when($user_id, function($query) use ($user_id) {
                return $query->where('id', $user_id);
            });
        })->first();
    }

    public function getSlackUserInfos($userData)
    {
        return $this->firstOrNew(['slack_user_id' => $userData->id]);
    }

    public function saveUserInfos($userInfo, $firstName, $lastName, $userData)
    {
        $userInfo->first_name = $firstName;
        $userInfo->last_name = $lastName;
        $userInfo->email = $userData->email;
        $userInfo->slack_user_id = $userData->id;
        $userInfo->save();
        return $userInfo;
    }
}

