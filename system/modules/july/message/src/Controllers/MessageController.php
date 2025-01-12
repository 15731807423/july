<?php

namespace July\Message\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Lang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use July\Message\Message;
use July\Message\MessageForm;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = [
            'models' => Message::index(),
            'context' => [
                'molds' => MessageForm::query()->pluck('label', 'id')->all(),
                'languages' => Lang::getTranslatableLangnames(),
                'langcode' => langcode('frontend'),
            ],
            'langcode' => langcode('frontend'),
        ];

        return view('message::message.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \July\Message\MessageForm  $form
     * @return \Illuminate\Http\Response
     */
    public function send(Request $request, MessageForm $form)
    {
        // 是否接口请求
        $api = $request->boolean('api', false);

        // 获取验证规则
        [$rules, $messages, $fields] = $form->resolveFieldRules();

        // dd($rules, $messages, $fields);

        // 生成验证器
        $validator = Validator::make($attributes = $request->all(), $rules, $messages);

        // 执行验证，如果未通过，则返回验证错误页
        if ($validator->fails()) {
            if ($api) {
                return ['status' => false, 'message' => $validator->errors()->first()];
            }

            return view('message::failed', [
                'errors' => $validator->errors()->messages(),
                'fields' => $fields,
            ]);
        }

        $referer = $request->header('referer');
        $referer = parse_url($referer, PHP_URL_PATH);
        $referer = explode('/', $referer)[1] ?? null;

        if (in_array($referer, array_keys(config('lang.available')))) {
            $langcode = $referer;
        } else {
            $langcode = langcode('frontend');
        }

        $ip = $request->header('CF-Connecting-IP') ?? $request->ip();

        // 补全字段值
        $attributes = array_merge($attributes, [
            'mold_id' => $form->getKey(),
            'langcode' => $langcode,
            'ip' => $ip,
            'user_agent' => $this->getUserAgent(),
            'trails' => $attributes['trails'] ?? $attributes['track_report'] ?? $attributes['trace_report'] ?? null,
            '_server' => $request->server(),
        ]);

        // 保存消息到数据库
        $message = Message::create($attributes);

        $count = Message::whereDate('created_at', date('Y-m-d'))->where('ip', $ip)->count();

        if ($count > 3) {
            if ($api) {
                return ['status' => false, 'message' => 'The maximum number of emails sent has been reached.'];
            }

            return view('message::failed', [
                'errors' => ['verify' => ['The maximum number of emails sent has been reached.']],
                'fields' => [],
            ]);
        }

        // 以邮件方式发送消息
        if (! $message->sendMail()) {
            Log::info($attributes);
        }

        if ($api) {
            return ['status' => true, 'message' => 'Message has been sent! We will contact you soon.'];
        }

        // 获取返回网址
        $backTo = $request->input('_back_to') ?? URL::previous();

        // 返回成功页面，在此页面中嵌入返回地址
        return view('message::success', ['back_to' => $backTo]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \July\Message\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function show(Message $message)
    {
        return $message->render('details');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \July\Message\MessageForm  $form
     * @return \Illuminate\Http\Response
     */
    public function create(MessageForm $form)
    {
        //
    }

    /**
     * 展示编辑或翻译界面
     *
     * @param  \July\Message\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function edit(Message $message)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \July\Message\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Message $message)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \July\Message\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function destroy(Message $message)
    {
        // Log::info($node->id);
        $message->delete();

        return response('');
    }

    /**
     * 获取用户代理描述
     *
     * @return string
     */
    public function getUserAgent()
    {
        $agent = new Agent();

        $browser = $agent->browser();
        $browserVersion = $agent->version($browser);
        $platform = $agent->platform();
        $platformVersion = $agent->version($platform);

        return "{$browser}[{$browserVersion}] on {$platform}[{$platformVersion}]";
    }
}
