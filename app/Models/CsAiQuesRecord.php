<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CsAiQuesRecord extends Model
{
    use HasFactory;

    protected $table = "cs_aiquesrecord";

    protected $guarded = [];
    public $timestamps = false;

    public function csAiQuesBank()
    {
        return $this->belongsTo(CsAiQuesBank::class, 'Qid', 'ID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }
}
