<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CsAiQuesBank extends Model
{
    use HasFactory;

    protected $table = "cs_aiquesbank";

    protected $primaryKey = 'ID';

    protected $guarded = [];

    public $timestamps = false;

    public function csAiQuesRecord()
    {
        return $this->hasMany(CsAiQuesRecord::class, 'Qid', 'id');
    }

    /**
     * 使用者資料寫入出題記錄
     *
     * @param Boolean $rightOrWrong 答案正確還是錯
     * @param String $userAnswer 答案
     * @param integer $userId 使用者id
     * @return String 使用者回答時間
     */
    public function insertUserDate($rightOrWrong, $userAnswer, $userId)
    {
        $now = date("Y-m-d H:i:s");
        $this->csAiQuesRecord()->where('user_id', $userId)->orderBy('create_ques', 'desc')->update(['is_right' => $rightOrWrong, 'user_answer' => $userAnswer, 'create_answer' => $now]);

        return $now;
    }
}
