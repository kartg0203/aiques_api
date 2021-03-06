<?php

namespace App\Http\Controllers\AiLearn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\CsAiQuesBank;
use App\Models\CsAiQuesRecord;
use App\Models\CsGreeting;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AiLearnController extends Controller
{
    /**
     * 查看是今天否有紀錄
     *
     */
    public function todayRecord(Request $request)
    {
        $greeting = [];
        $nextOptions = [];
        $ending = [];
        $learnContent = [];
        $quesOptions = [];
        $recordID = '';
        $type = 'noRecord';
        $user = $request->user();
        $today = date('Y/m/d');
        // 判斷用戶在出題記錄表有無資料
        if ($user->csAiQuesRecords()->exists()) {
            // 判斷今天是否作答過
            $is_record = $user->csAiQuesRecords()->whereDate('created_ques', date('Y-m-d'))->exists();
            if ($is_record) {
                $records = $user->csAiQuesRecords()->whereDate('created_ques', date('Y-m-d'))->oldest('created_ques')->get();
                $languageOptionTmp = [];
                $todayQuesCount = 1;

                foreach ($records as $index => $record) {
                    // 開頭
                    if ($record->greeting) {
                        // 招呼
                        $greeting['position'] = 'left';
                        foreach (json_decode($record->greeting, true) as $greet) {
                            foreach ($greet['script'] as $value) {
                                $greeting['greet'][] = $value;
                            }
                        };
                        $greeting = $this->setTime($greeting, $record->created_ques);
                        // 選擇語言
                        $languageOptions = json_decode($record->language_option, true);
                        $subjectCH = ['英文題目', '日文題目'];
                        $subject = [];
                        foreach ($languageOptions['language'] as $value) {
                            $subject[$value] = $subjectCH[$value];
                        };
                        $nextContent = '';

                        $nextOptions = [
                            'position' => $languageOptions['type'] == 'skip' ? 'skip' : 'left',
                            'nextOptions' => [
                                'options' => [
                                    'type' => $languageOptions['type'],
                                    'lang' => $subject,
                                    'content' => $nextContent,
                                    'clicked' => false,
                                ]
                            ],
                        ];
                        $type = 'greet';
                    }

                    // 題目
                    $ques = CsAiQuesBank::where('id', $record->Qid)->first();
                    if ($ques) {
                        // *******
                        $nextOptions['nextOptions']['options']['clicked'] = true;
                        if (empty($languageOptionTmp) == false) {

                            // 回答
                            if ($languageOptionTmp['type'] == 'next') {
                                $answerContent = '下一題';
                                $answerType = 'word';
                                $answerPosition = 'right';
                            } elseif ($languageOptionTmp['type'] == 'options') {
                                $subjectCH = ['英文題目', '日文題目'];
                                $answerContent = $subjectCH[$ques->lang];
                                $answerType = 'word';
                                $answerPosition = 'right';
                            } elseif ($languageOptionTmp['type'] == 'skip') {
                                $answerContent = null;
                                $answerType = 'skip';
                                $answerPosition = 'no';
                            }
                            $learnContent["reply_{$index}"] = [
                                'position' => $answerPosition,
                                'answer' => [
                                    'reply' => [
                                        'type' => $answerType,
                                        'content' => $answerContent,
                                    ]
                                ]
                            ];

                            // 有回答代表案過選項按鈕，把它設為不能再按
                            $indexDecrement = $index - 1;
                            $learnContent["nextOptions_{$indexDecrement}"]['nextOptions']['options']['clicked'] = true;
                        }

                        // *****
                        // 題目
                        $ques->question = nl2br($ques->question);

                        $categoryContent = ($todayQuesCount > 1 ? '' : '開始囉!') . "第{$todayQuesCount}題({$ques->category})";
                        $learnContent["question_{$index}_0"] = [
                            'position' => 'left',
                            'question' => [
                                'category' => [
                                    'type' => 'word',
                                    'content' => $categoryContent,
                                ],
                                'question' => [
                                    'type' => ($ques->source && $ques->voice_intonation) ? 'voice' : 'word',
                                    'title' => ($ques->source && $ques->voice_intonation) ? $ques->source : null,
                                    'content' => ($ques->source && $ques->voice_intonation) ? "{$ques->voice_intonation}/{$ques->question}" : $ques->question,
                                ],
                            ]
                        ];

                        // 製作選項
                        foreach (json_decode($ques->options, true) as $key => $value) {
                            $learnContent["question_{$index}_0"]['question'][$key] = [
                                'type' => 'word',
                                'content' => "({$key}) {$value}",
                            ];
                        };
                        $learnContent["question_{$index}_0"] = $this->setTime($learnContent["question_{$index}_0"], $record->created_ques);
                        // 使用者的選項
                        $quesOptions = json_decode($ques->options, true);
                        $type = 'question';

                        $recordID = $record->id;
                        // 答案
                        if ($record->user_answer && $record->created_answer) {
                            $learnContent["answer_{$index}"] = [
                                'position' => 'right',
                                'answer' => [
                                    'answer' => [
                                        'type' => 'word',
                                        'content' => $record->user_answer,
                                    ],
                                ],
                            ];

                            $languageOption = json_decode($record->language_option, true);
                            if ($languageOption) {
                                if ($languageOption['type'] == 'options') {
                                    $nextContent = '您還有其他語言題目可以做，請問要繼續嗎?';
                                    $subjectCH = ['英文題目', '日文題目'];
                                    foreach ($languageOption['language'] as $key => $value) {
                                        $optionsTmp[$value] = $subjectCH[$value];
                                    }
                                    $options = $optionsTmp;
                                } elseif ($languageOption['type'] == 'next') {
                                    $nextContent =  '點我進行下一題';
                                    $options = $languageOption['language'];
                                }
                                $nextType = $languageOption['type'];
                                $nextPosition = 'left';
                            } else {
                                $nextType = 'end';
                                $nextContent = null;
                                $options = null;
                                $nextPosition = 'end';
                            }
                            $learnContent["question_{$index}_1"] = [
                                'position' => 'left',
                                'question' => [
                                    'correctAnswer' => [
                                        'type' => 'word',
                                        'content' => $record->is_right ? "正確答案是: {$ques->answer}, 恭喜您答對了!!!" : "很可惜, 正確答案是: {$ques->answer}",
                                    ],
                                    'translation' => [
                                        'type' => 'word',
                                        'content' => $ques->translation,
                                    ],
                                    'parsing' => [
                                        'type' => 'word',
                                        'content' => $ques->parsing,
                                    ]
                                ],
                            ];
                            $learnContent["question_{$index}_1"] = $this->setTime($learnContent["question_{$index}_1"], $record->created_answer);
                            $learnContent["nextOptions_{$index}"] = [
                                'position' => $nextPosition,
                                'nextOptions' => [
                                    'options' => [
                                        'type' => $nextType,
                                        'lang' => $options,
                                        'content' => $nextContent,
                                        'clicked' => false,
                                    ]
                                ]
                            ];
                            $type = 'answer';
                        }
                        $todayQuesCount += 1;
                    }

                    // 結尾
                    if ($record->ending) {
                        $ending['position'] = 'left';
                        foreach (json_decode($record->ending, true) as $end) {
                            foreach ($end['script'] as $value) {
                                $ending['end'][] = $value;
                            }
                        };
                        $ending = $this->setTime($ending, $record->created_answer);
                        $type = 'end';
                    }
                    // 暫存上一題選項
                    if ($record->language_option) {
                        $languageOptionTmp = json_decode($record->language_option, true);
                    }
                }

                $data = [
                    'greeting' => $greeting,
                    'nextOptions' => $nextOptions,
                    'learnContent' => $learnContent,
                    'ending' => $ending,
                    'options' => $quesOptions,
                    'type' => $type,
                    'recordID' => $recordID,
                ];
                return $this->jsonSuccessData($data);
                // return response()->json(['status' => true, 'greeting' => $greeting, 'nextOptions' => $nextOptions, 'learnContent' => $learnContent, 'ending' => $ending, 'options' => $quesOptions, 'type' => $type, 'recordID' =>  $recordID], JSON_UNESCAPED_UNICODE);
            } else {
                // 今天沒紀錄
                return $this->jsonSuccessData(['type' => $type], 200, '今天沒紀錄');
            }
        } else {
            // 新使用者
            return $this->jsonSuccessData(['type' => $type], 200, '新用戶, 無紀錄');
        }
    }

    /**
     *
     *開頭招呼
     * @return Array  開頭招呼
     */
    public function getGreet(Request $request)
    {
        $greeting = [];
        $now = date('Y-m-d H:i:s');

        $pay = false;
        $class = 1;
        $system = 1;
        $user = $request->user();



        // 弱項題型及未上線練習擇一，未上線練習優先
        $latestClassDate = $user->csAiQuesRecords()->latest('created_ques')->value('created_ques');
        $chkLearnDate = null;
        $diffInDay = '';
        if ($latestClassDate) {
            $latestClassDate = Carbon::parse(mb_substr($latestClassDate, 0, 10, 'UTF-8'));
            $aWeekAgo = today()->subWeek();
            // $latestClassDate = today()->modify('-30 days');
            if ($latestClassDate->lte($aWeekAgo)) {
                $chkLearnDate = CsGreeting::where([['disabled', 0], ['position', 'start'], ['sort', '12'], ['chkCond', 'chkLearnDate']])->inRandomOrder()->limit(1);
                $diffInDay = $latestClassDate->diffInDays(today());
            }
        }
        // 假如練習時間為空可以來判斷題型
        $chkWeakItem = null;
        $weaknessCategory = [];
        if (empty($diffInDay) && empty($chkLearnDate)) {
            $aMonthAgo = today()->subMonths();
            // 要抓做過的題型裡答錯3題以上>=的
            // $quesWrong = $user->csAiQuesRecords()->join('cs_aiquesbank', 'Qid', 'cs_aiquesbank.id')
            //     ->selectRaw("category, COUNT(category) as countCategory, COUNT(is_right = false OR null) as countWrong, round((COUNT(is_right = true OR null)/COUNT(category)) * 100) as hitRate")->where('cs_aiquesbank.lang', 0)->whereDate('created_answer', '>', $aMonthAgo)->groupBy('category')->having('countWrong', '>=', '3')->get();
            $quesWrong = $user->csAiQuesRecords()->join('cs_aiquesbank', 'Qid', 'cs_aiquesbank.id')
                ->selectRaw("category, COUNT(is_right = false OR null) as countWrong")->where('cs_aiquesbank.lang', 0)->whereDate('created_answer', '>', $aMonthAgo)->groupBy('category')->having('countWrong', '>=', '3')->pluck('countWrong', 'category');

            if ($quesWrong->isNotEmpty()) {
                $chkWeakItem = CsGreeting::where([['disabled', 0], ['position', 'start'], ['sort', '14'], ['chkCond', 'chkWeakItem']])->inRandomOrder()->limit(1);

                $categoryCH = DB::connection(env('DB_DATABASE_2'))->table('cs_qtype')->where('status', 1)->pluck('sName', 'sCode');
                $quesWrong->each(function ($value, $key) use ($categoryCH, &$weaknessCategory) {
                    array_push($weaknessCategory, $categoryCH[$key]);
                });
            }
        }
        // ***天數及題型弱項end***

        // ***判斷有無繳費***
        $yearMonth = today()->format('Ym');
        $nonpayment = DB::table('cs_accrec_view_users')->where([['UID', $user->UID], ['pMonth', 'like', "%{$yearMonth}%"]])->first();
        $chkPay = null;
        if ($nonpayment) {
            $pMonth = $nonpayment->pMonth;
            $pStatus = $nonpayment->pStatus;
            if (today()->gt(Carbon::parse($pMonth)->addWeek()) && $pStatus == 'N') {
                $chkPay = CsGreeting::where([['disabled', 0], ['position', 'start'], ['sort', '30']])->inRandomOrder()->limit(1);
            }
        }
        // ***繳費end***

        // ***判斷今天上課提醒***
        $reservedClassTable = [
            [
                'DB' => env('DB_DATABASE'),
                'table' => 'cs_classdata',
                'date' => 'sDate',
                'classType' => 'uType',
                'UID' => 'stdID'
            ],
            [
                'DB' => env('DB_DATABASE_2'),
                'table' => 'cs_greservation_gcview',
                'date' => 'uDate',
                'classType' => 'gType',
                'UID' => 'rvID',
            ],
            [
                'DB' => env('DB_DATABASE_2'),
                'table' => 'cs_greservation_grview',
                'date' => 'uDate',
                'classType' => 'gType',
                'UID' => 'rvID',
            ]
        ];

        $todayClass = [];
        foreach ($reservedClassTable as $item) {
            $reservedClass = DB::connection($item['DB'])->table($item['table'])->select("{$item['classType']} as classType", 'ID')->where($item['UID'], $user->UID)->whereDate($item['date'], today())->get();
            if ($reservedClass->isNotEmpty()) {

                $reservedClass->each(function ($class) use (&$todayClass) {
                    array_push($todayClass, config("constants.CLASSTYPE.{$class->classType}"));
                });
            }
        }
        $chkClass = null;
        if (!empty($todayClass)) {
            $chkClass = CsGreeting::where([['disabled', 0], ['position', 'start'], ['sort', '40']])->inRandomOrder()->limit(1);
        }
        // ***上課提醒end***

        // sql 查詢
        $greet = CsGreeting::where([['disabled', 0], ['position', 'start'], ['sort', '10'], ['chkCond', 'chkGreet']])->inRandomOrder()->limit(1);
        $greetUnion = CsGreeting::where([['disabled', 0], ['position', 'start']])
            ->where(function ($query) {
                $query->where('sort', '20')
                    ->orWhere('sort', '50');
            })
            ->union($greet)
            ->when($chkLearnDate, function ($query) use ($chkLearnDate) {
                $query->unionAll($chkLearnDate);
            })->when($chkWeakItem, function ($query) use ($chkWeakItem) {
                $query->unionAll($chkWeakItem);
            })->when($chkPay, function ($query) use ($chkPay) {
                $query->unionAll($chkPay);
            })->when($chkClass, function ($query) use ($chkClass) {
                $query->unionAll($chkClass);
            })
            ->get();

        $sortGreeting = $greetUnion->sortBy('sort');
        $greeting['position'] = 'left';
        foreach ($sortGreeting as $greet) {
            $script = json_decode($greet['script'], true);

            if (in_array($greet['sort'], config('constants.GREETSORT'))) {
                foreach ($script as $key => $value) {
                    if ($script[$key]['type'] == 'word') {
                        switch ($greet['chkCond']) {
                            case 'chkGreet':
                                $script[$key]['content'] = str_ireplace('[user]', $user->Utitle, $script[$key]['content']);
                                break;
                            case 'chkLearnDate':
                                $script[$key]['content'] = str_ireplace('[days]', $diffInDay, $script[$key]['content']);
                                break;
                            case 'chkWeakItem':
                                $script[$key]['content'] = str_ireplace('[weak]', " [" . implode("、", $weaknessCategory) . "]", $script[$key]['content']);
                                break;
                            case 'chkPay':
                                $script[$key]['content'] = str_ireplace('[date]', Carbon::parse($pMonth)->format('Y/m'), $script[$key]['content']);
                                break;
                            case 'chkTodayClass':
                                $script[$key]['content'] = str_ireplace('[class]', " [" . implode("、", $todayClass) . "]", $script[$key]['content']);
                                break;
                        }
                    }
                }
            }

            // if ($greet['sort'] == '1') {
            //     // 問候語
            //     foreach ($script as $key => $value) {
            //         if ($script[$key]['type']) {
            //             switch ($greet['chkCond']) {
            //                 case 'chkGreet':
            //                     $script[$key]['content'] = str_replace('U', $user->Utitle, $script[$key]['content']);
            //                     break;
            //                 case 'chkLearnDate':
            //                     $script[$key]['content'] = str_replace('N', $diffInDay, $script[$key]['content']);
            //                     break;
            //                 case 'chkWeakItem':
            //                     $script[$key]['content'] = str_replace('W', " [" . implode("、", $weaknessCategory) . "]", $script[$key]['content']);
            //                     break;
            //             }
            //         }
            //     }
            // }

            // 繳款提醒
            // if ($greet['sort'] == '3') {
            //     foreach ($script as $key => $value) {
            //         if ($value['type'] == 'word') {
            //             $script[$key]['content'] = str_replace('D', Carbon::parse($pMonth)->format('Y/m'), $script[$key]['content']);
            //         }
            //     }
            // }

            // 今天上課提醒
            // if ($greet['sort'] == '4') {
            //     foreach ($script as $key => $value) {
            //         if ($value['type'] == 'word') {
            //             $script[$key]['content'] = str_replace('C', " [" . implode("、", $todayClass) . "]", $script[$key]['content']);
            //         }
            //     }
            // }

            $greetingAll[] = [
                'sort' => $greet['sort'],
                'script' =>  $script,
            ];
        };

        $greetJson = json_encode($greetingAll, JSON_UNESCAPED_UNICODE);
        // 給前端的
        foreach ($greetingAll as $greet) {
            foreach ($greet['script'] as $value) {
                $greeting['greet'][] = $value;
            }
        }

        // 看使用者有沒有可選語言
        $lang = array_keys(json_decode($user->ques_limit, true));
        $limitType = count($lang) > 1 ? 'options' : 'skip';
        $nextOptions = $this->nextOptions($user, $lang, $limitType);

        $subjectCH = ['英文題目', '日文題目'];
        $subject = [];
        foreach ($nextOptions['language'] as $value) {
            $subject[$value] = $subjectCH[$value];
        };

        $nextContent = '';
        // $greeting['greet']['next'] =  [
        //     'type' => $nextOptions['type'],
        //     'lang' => $subject,
        //     'content' => $nextContent,
        // ];
        $next = [
            'position' => $nextOptions['type'] == 'skip' ? 'skip' : 'left',
            'nextOptions' => [
                'options' => [
                    'type' => $nextOptions['type'],
                    'lang' => $subject,
                    'content' => $nextContent,
                    'clicked' => false,
                ],
            ],
        ];

        $langOption = json_encode($nextOptions);

        $record = CsAiQuesRecord::create([
            'created_ques' => $now,
            'user_id' => $user->ID,
            'greeting' => $greetJson,
            'language_option' => $langOption
        ]);
        $greeting = $this->setTime($greeting, $now);

        return $this->jsonSuccessData(['greeting' => $greeting, 'nextOptions' => $next]);
    }

    /**
     * 隨機出題
     */
    public function randQues(Request $request)
    {

        $question = [];
        $answer = [];
        $user = $request->user();
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $type = $request->type;
        $lang = $request->lang;
        // true 為停下來， false為跑出題，今天有題目但是未作答的是true
        $status = $user->csAiQuesRecords()->whereDate('created_ques', $today)->whereNull('user_answer')->whereNotNull('Qid')->exists();
        if ($status) {
            return $this->jsonSuccessData(['state' => 'wait']);
        }

        // 抓今天做得題數
        $todayLang = CsAiQuesBank::selectRaw('COUNT(cs_aiquesbank.lang) as count, cs_aiquesbank.lang')->join('cs_aiquesrecord', 'cs_aiquesbank.ID', '=', 'cs_aiquesrecord.Qid')
            ->where('cs_aiquesrecord.user_id', $user->ID)
            ->whereDate('cs_aiquesrecord.created_ques', $today)
            ->groupBy('lang')->pluck('count', 'lang');
        // ->groupBy('cs_aiquesbank.lang')->get();

        // 判斷使用者的語言題目
        $qLimitArray = json_decode($user->ques_limit, true);

        $todayTotal = $user->csAiQuesRecords()->whereDate('created_answer', $today)->count();
        // 題數已全部用完，沒題目可做了
        if (array_sum($qLimitArray) -  $todayTotal == 0) {
            return $this->jsonSuccessData(['state' => 'over']);
            // return response()->json([
            //     'state' => 'end',
            //     'answer' => $answer,
            //     'question' => $question,
            //     'ending' => $ending,
            //     'options' => [],
            //     'recordID' => [],
            // ]);
        }
        $result = [];
        // 目前答題狀況
        if ($todayLang->isNotEmpty()) {
            // 第一題以外。要開始限制題目數
            // 判斷使用者的語言題目做完了嗎
            if (isset($todayLang[$lang])) {
                foreach ($qLimitArray as $key => $value) {
                    if (($value - $todayLang[$key]) != 0) {
                        // $quesState = true;
                        $result = $this->getQuestion($user, $lang, $qLimitArray);
                        $state = 'middle';
                        break;
                    } else {
                        $state = 'end';
                    }
                }
            } elseif (isset($qLimitArray[$lang])) {
                $result = $this->getQuestion($user, $lang, $qLimitArray);
                $state = 'middle';
            } else {
                $state = 'end';
            }
        } else {
            // 第一題
            $result = $this->getQuestion($user, $lang, $qLimitArray);
            $state = 'start';
        }
        // ***
        if ($result) {
            $ques = $result[0];
            $record = $result[1];
        }

        if ($state != 'end') {
            // 回答
            if ($type == 'next') {
                $answerContent = '下一題';
                $answerType = 'word';
                $answerPosition = 'right';
            } elseif ($type == 'options') {
                $subjectCH = ['英文題目', '日文題目'];
                $answerContent = $subjectCH[$lang];
                $answerType = 'word';
                $answerPosition = 'right';
            } elseif ($type == 'skip') {
                $answerType = 'skip';
                $answerContent = null;
                $answerPosition = 'skip';
            }
            $answer = [
                'position' => $answerPosition,
                'answer' => [
                    'reply' => [
                        'type' => $answerType,
                        'content' => $answerContent,
                    ]
                ]
            ];
            // 題目
            $ques->question = nl2br($ques->question);
            $ques->qTime = $now;
            $ques->options = json_decode($ques->options, true);
            $todayQuesCount = $user->csAiQuesRecords()->whereDate('created_ques', $today)->whereNotNull('Qid')->count();
            $categoryContent = ($todayQuesCount > 1 ? '' : '開始囉!') . "第{$todayQuesCount}題({$ques->category})";
            $question = [
                'position' => 'left',
                'question' => [
                    'category' => [
                        'type' => 'word',
                        'content' => $categoryContent,
                    ],
                    'question' => [
                        'type' => ($ques->source && $ques->voice_intonation) ? 'voice' : 'word',
                        'title' => ($ques->source && $ques->voice_intonation) ? $ques->source : null,
                        'content' => ($ques->source && $ques->voice_intonation) ? "{$ques->voice_intonation}/{$ques->question}" : $ques->question,
                    ],
                ]
            ];
            // 製作選項
            foreach ($ques->options as $key => $value) {
                $question['question'][$key] = [
                    'type' => 'word',
                    'content' => "({$key}) {$value}",
                ];
            };
        }

        $question = $this->setTime($question, $record->created_ques);
        $data =
            [
                'state' => $state,
                'answer' => $answer,
                'question' => $question,
                'options' => $ques->options,
                'recordID' => $record->id,
            ];
        return $this->jsonSuccessData($data);
    }

    // 拿題目
    function getQuestion($user, $lang)
    {

        $now = date('Y-m-d H:i:s');
        // 抓學生做對題目
        $rightQid = $user->csAiQuesRecords()->where('is_right', true)->pluck('Qid');


        // 學生總筆數，是為了音檔的
        $totalCount = $user->csAiQuesRecords()->count();
        if ($totalCount == 0) {
            $newTotal = 1;
        } else {
            $newTotal = $totalCount % 6;
        }
        // 先不要讓音檔出來
        $newTotal = 1;

        // 抓學生弱類
        $category = $this->getCategory($user);

        $ques = CsAiQuesBank::where('status', 1)->when($newTotal, function ($ques) use ($category, $lang) {
            $ques->when($lang, function ($ques) {
                $ques->where('lang', 1);
            }, function ($ques) use ($category) {
                $ques->where([['category', $category], ['lang', 0]]);
            });
        }, function ($ques) {
            $ques->where('category', 'VO');
        })->whereIntegerNotInRaw('id', $rightQid)->select('id', 'category', 'question', 'options', 'voice_intonation', 'source')->inRandomOrder()->first();
        // $ques->selectLang = $ques->lang == 0 ? 'S001' : 'S002';
        $record = $ques->csAiQuesRecord()->create([
            'created_ques' => $now,
            'user_id' => $user->ID,
        ]);

        return [$ques, $record];
    }

    /**
     * 獲取學生的弱點英文大類
     */
    function getCategory($user)
    {
        // $user = User::find(auth()->user()->id);
        if ($user->userScore()->doesntExist()) {
            $user->userScore()->create();
        }
        $category = $user->userScore()->select('VER', 'ADJ', 'ADV', 'MEW', 'PRD', 'NOU', 'BEE', 'MOD', 'CON', 'CNJ', 'PRP', 'GER', 'XXX', 'CAU', 'REP', 'PAS', 'CLA', 'ACT')->first();
        $category = $category->toArray();
        asort($category);
        $category = array_slice($category, 0, 3);
        $sum = array_sum($category);
        $newCategory = array_map(function ($num) use ($sum) {
            if ($num < 100) {
                return round(((100 - $num) / $sum) * 100, 1);
            } else {
                return round(($num / $sum) * 100, 1);
            }
        }, $category);

        $result = '';
        // 機率數組的總機率精度
        $newSum = array_sum($newCategory);
        // 機率數組迴圈
        foreach ($newCategory as $key => $typeNum) {
            $randNum = mt_rand(1, $newSum);
            // 抽取隨機數

            if ($randNum <= $typeNum) {
                $result = $key;
                // 得出結果
                break;
            } else {
                $newSum -= $typeNum;
            }
        }
        unset($newCategory);
        return $result;
    }

    /**
     * 使用者回答答案
     */
    public function answer(Request $request)
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $user = $request->user();
        $langOption = null;
        // $recordID = $request->recordID;
        $recordID = $request->id;
        $ending = [];

        $validator = Validator::make(
            $request->all(),
            [
                'uAnswer' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            ],
            [
                'required' => ':attribute不能為空',
                'in' => ':attribute 選項格式不符',
            ],
            [
                'uAnswer' => '答案',
            ]
        );
        if ($validator->fails()) {
            return $this->jsonErrorsData(400, '答案輸入錯誤', $validator->errors());
        }
        // 紀錄抓剛剛出現的題目
        $record = $user->csAiQuesRecords()->where('id', $recordID)->firstOrFail();
        if (Str::of($record->user_answer)->isNotEmpty()) {
            return $this->jsonSuccessData(['state' => 'stop'], 200, '此題您以作答');
            // return response()->json(['status' => false, 'error' => ['message' => '此題您以作答']]);
        }
        $answer = $request->uAnswer;
        // 問題
        $ques = CsAiQuesBank::select('translation', 'parsing', 'answer', 'category', 'lang')->where('id', $record->Qid)->firstOrFail();
        $jpCategory = ['jp1', 'jp2', 'jp3'];
        if ($ques->answer == $answer) {
            $is_right = true;
            $correctAnswer = "正確答案是: {$ques->answer}, 恭喜您答對了!!!";
            if ($ques->category != 'VO' && in_array($ques->category, $jpCategory) == false) {
                if ($user->userScore()->whereBetween($ques->category, [0.5, 99.5])->exists()) {
                    $user->userScore()->increment($ques->category, 0.5);
                }
            }
        } else {
            $is_right = false;
            $correctAnswer = "很可惜, 正確答案是: {$ques->answer}";
            if ($ques->category != 'VO' && in_array($ques->category, $jpCategory) == false) {
                if ($user->userScore()->whereBetween($ques->category, [0.5, 99.5])->exists()) {
                    $user->userScore()->decrement($ques->category, 0.5);
                }
            }
        }
        // 今天各語言做的題數
        $todayLang = CsAiQuesBank::selectRaw('COUNT(cs_aiquesbank.lang) as count,  cs_aiquesbank.lang')->join('cs_aiquesrecord', 'cs_aiquesbank.ID', '=', 'cs_aiquesrecord.Qid')
            ->where('cs_aiquesrecord.user_id', $user->ID)
            ->whereDate('cs_aiquesrecord.created_ques', $today)
            ->groupBy('lang')->pluck('count', 'lang');


        // 判斷使用者的語言題目做完了嗎
        $qLimitArray = json_decode($user->ques_limit, true);
        foreach ($todayLang as $key => $value) {
            if ($qLimitArray[$key] - $value == 0) {
                unset($qLimitArray[$key]);
                unset($todayLang[$key]);
                $options = array_keys($qLimitArray);
                if (empty($options)) {
                    $limitType = 'end';
                } else {
                    $limitType = 'options';
                }
            } else {
                $options = array_keys($todayLang->toArray());
                $limitType = 'next';
            }
        }

        // 我今天做的題數
        $todayCount = $user->csAiQuesRecords()->whereDate('created_ques', $today)->whereNotNull('Qid')->count();
        // 我所有類型能做的題數的合
        $quesLimit =  array_sum(json_decode($user->ques_limit, true));
        if ($todayCount < $quesLimit) {
            $nextOptions = $this->nextOptions($user, $options, $limitType);
            $langOption = json_encode($nextOptions);
            $nextType = $nextOptions['type'];
            $nextContent = $nextOptions['type'] == 'options' ? '您還有其他語言題目可以做，請問要繼續嗎?' : '點我進行下一題';
            $nextPosition = 'left';
        } elseif ($todayCount == $quesLimit) {
            $langOption = null;
            $nextType = 'end';
            $nextContent = null;
            $nextPosition = 'end';
        }

        $record->update(['is_right' => $is_right, 'user_answer' => $answer, 'created_answer' => $now, 'language_option' => $langOption]);

        $answer = [
            'position' => 'right',
            'answer' => [
                'answer' => [
                    'type' => 'word',
                    'content' => $answer,
                ],
            ],
        ];

        if ($limitType == 'options') {
            $subjectCH = ['英文題目', '日文題目'];
            foreach ($options as $key => $value) {
                $optionsTmp[$value] = $subjectCH[$value];
            }
            $options = $optionsTmp;
        }

        $question = [
            'position' => 'left',
            'question' => [
                'correctAnswer' => [
                    'type' => 'word',
                    'content' => $correctAnswer,
                ],
                'translation' => [
                    'type' => 'word',
                    'content' => $ques->translation,
                ],
                'parsing' => [
                    'type' => 'word',
                    'content' => $ques->parsing,
                ]
            ],
        ];
        $question = $this->setTime($question, $record->created_answer);
        $next = [
            'position' => $nextPosition,
            'nextOptions' => [
                'options' => [
                    'type' => $nextType,
                    'lang' => $options,
                    'content' => $nextContent,
                ]
            ]
        ];
        $state = 'continue';
        if ($nextType == 'end') {
            // 沒題目
            $ending = $this->getEnd($record);
            $ending = $this->setTime($ending, $record->created_answer);
            $state = 'end';
        }
        $data = [
            'state' => $state,
            'recordID' => $record->id,
            'answer' => $answer,
            'question' => $question,
            'nextOptions' => $next,
            'ending' => $ending,
        ];
        return $this->jsonSuccessData($data);
        // return response()->json([
        //     'status' => true,
        //     'state' => $state,
        //     'recordID' => $record->id,
        //     'answer' => $answer,
        //     'question' => $question,
        //     'nextOptions' => $next,
        //     'ending' => $ending,
        // ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 下一題的選項判斷
     */
    public function nextOptions($user, $lang = [], $limitType = '')
    {

        if ($limitType == 'end') {
            $type = '';
            $content = [];
            return null;
        } else {
            $type = $limitType;
            $content = $lang;
        }
        // 看使用者有沒有可選語言
        $nextOption =
            [
                'type' => $type,
                'language' => $content,
            ];

        return $nextOption;
    }

    /**
     * 結尾招呼定行文
     *
     * @return array 結尾招呼
     */
    public function getEnd($record)
    {
        $ending = [];
        $pay = 0;
        $class = 0;
        $system = 0;
        // 先查隨機問候語
        $end = CsGreeting::where([['disabled', 0], ['position', 'end'], ['sort', '10']])->inRandomOrder()->limit(1);
        // 在查剩下的
        $endingUnion = CsGreeting::where([['disabled', 0], ['position', 'end']])
            ->where('sort', '2')
            ->when($pay, function ($query) {
                return $query->orWhere('sort', '3');
            })->when($class, function ($query) {
                return $query->orWhere('sort', '4');
            })->when($system, function ($query) {
                return $query->orWhere('sort', '5');
            })
            ->union($end)
            ->get();
        // 寫進資料庫
        $sortEnding = $endingUnion->sortByDesc('sort');
        $endingAll = [];
        foreach ($sortEnding as $end) {
            $endingAll[] = [
                'sort' => $end['sort'],
                'script' => json_decode($end['script'], true),
            ];
        };
        $endJson = json_encode($endingAll, JSON_UNESCAPED_UNICODE);
        $record->update(['ending' => $endJson]);
        // *****
        $ending['position'] = 'left';
        foreach ($sortEnding as $end) {
            $script = json_decode($end['script'], true);
            foreach ($script as $value) {
                $ending['end'][] = $value;
            }
        };
        return $ending;
    }

    /**
     * 在對話的最後一個設時間
     */
    public function setTime($content, $time)
    {

        $key = array_key_last($content);
        $lastKey = array_key_last($content[$key]);
        $lastItem = $content[$key][$lastKey];
        $lastItem['time'] = $time;
        $content[$key][$lastKey] = $lastItem;
        return $content;
    }


    public function jsonDecode($greeting)
    {
        $greeting = json_decode($greeting, true);

        foreach ($greeting as $index => $greet) {
            $greeting[$index]['script'] = json_decode($greet['script'], true);
        }

        return $greeting;
    }
}
