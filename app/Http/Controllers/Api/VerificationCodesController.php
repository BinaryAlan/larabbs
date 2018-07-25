<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VerificationCodeRequest;
use Overtrue\EasySms\EasySms;

class VerificationCodesController extends Controller
{
    public function store(VerificationCodeRequest $request, EasySms $easySms)
    {
        $captchaData = \Cache::get($request->captcha_key);

        if (!$captchaData) {
            return $this->response->error('图片验证码已失效', 422);
        }

        if (!hash_equals($captchaData['code'], $request->captcha_code)) {
            // 验证错误就清除缓存
            \Cache::forget($request->captcha_key);
            return $this->response->errorUnauthorized('验证码错误');
        }

        $phone = $captchaData['phone'];

        if (!app()->environment('production')) {
            $code = '1234';
        } else {

            $code = str_pad(random_int(0, 9999), 4, 0, STR_PAD_LEFT);

            try {
                $result = $easySms->send($phone, [
                    'content' => "【lara社区】您的验证码是{$code}。如非本人操作，请忽略本短信",
                ]);
            } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                $response = $exception->getResponse();
                $result   = json_decode($response->getBody()->getContents(), true);
                return $this->response->errorInternal($result['msg'] ?? '短信发送异常');
            }
        }

        $key      = "verificationCode_" . str_random(15);
        $expireAt = now()->addMinutes(10);
        \Cache::put($key, ['phone' => $phone, 'code' => $code], $expireAt);
        \Cache::forget($request->captcha_key);
        return $this->response->array([
            'key'        => $key,
            'expired_at' => $expireAt->toDateTimeString()])->setStatusCode(201);
    }
}
