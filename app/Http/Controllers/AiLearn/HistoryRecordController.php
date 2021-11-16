<?php

namespace App\Http\Controllers\AiLearn;

use App\Http\Controllers\Controller;
use App\Models\CsAiQuesBank;
use App\Models\User;
use Illuminate\Http\Request;

class HistoryRecordController extends Controller
{
    /**
     *歷史頁面view
     *
     * @return Blade 歷史頁面模板畫面
     */
    public function historyIndex()
    {
        if ($user = User::find(auth()->user()->id)) {
            return view('AdminUnit.M_ailearn.ailearn_log', ['state' => true, 'userId' => $user->id]);
        } else {
            return view('AdminUnit.M_ailearn.ailearn_log', ['state' => false, 'userId' => '沒有此用戶']);
        }
    }

    /**
     * 歷史紀錄api
     *
     */
    public function historyRecord(Request $request)
    {
        $user = $request->user();
        $records = $user->csAiQuesRecords()->whereNotNull('user_answer')->latest('created_ques')->limit($user->record_limit)->get();
        if (count($records) > 0) {
            foreach ($records as $record) {
                $quesModel = CsAiQuesBank::select('question', 'category', 'translation', 'parsing', 'answer')->where('id', $record->Qid)->first();
                $quesModel->question = nl2br($quesModel->question);
                $quesModel->translation = nl2br($quesModel->translation);
                $quesModel->parsing = nl2br($quesModel->parsing);
                $ques[] = $quesModel;
                $ans[] = [
                    'user_answer' => $record->user_answer,
                    'aTime' => $record->created_answer,
                    'is_right' => $record->is_right,
                    'record_limit' => $user->record_limit,
                ];
            }
        } else {
            $ques = [];
            $ans = [];
        }
        return response()->json(['ques' => $ques, 'ans' => $ans], JSON_UNESCAPED_UNICODE);
    }
}
