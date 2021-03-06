<?php

namespace Cann\Admin\OAuth\ThirdAccount\Thirds;

use Cann\Admin\OAuth\Models\AdminUserThirdPfBind;

/**
 * 第三方通行证 抽象模型父类
 */
abstract class ThirdAbstract implements ThirdInterface
{
    protected $source;
    protected $redirectUrl;

    public function __construct(string $source)
    {
        $this->source = $source;
    }

    public function getPlatform()
    {
        return $this->source;
    }

    public function getAuthorizeUrl(array $params)
    {
        return '';
    }

    public function getRedirectUrl()
    {
        if (! $this->redirectUrl) {
            $this->redirectUrl = admin_url('/oauth/callback?source=' . $this->source);
        }

        return $this->redirectUrl;
    }

    public function setRedirectUrl(string $url)
    {
        $this->redirectUrl = $url;

        return $this;
    }

    public function getUserByThird(array $thirdUser)
    {
        if (! isset($thirdUser['id']) || ! $thirdUser['id']) {
            throw new \Exception('Invalid ThirdId');
        }

        $user = AdminUserThirdPfBind::getUserByThird($this->getPlatform(), $thirdUser['id']);


        // 根据第三方账号创建本地账号
        if (! $user && config('admin-oauth.allowed_auto_create_account')) {

            $userModel = config('admin.database.users_model');

            $user = $userModel::firstOrCreate([
                'username' => $thirdUser['full_info']['username'],
            ], [
                'name'     => $thirdUser['full_info']['name'],
                'avatar'   => $thirdUser['full_info']['avatar_url'] ?? '',
                'password' => '',
            ]);

            $this->bindUserByThird($user, $thirdUser);
        }

        return $user;
    }

    // 将指定第三方账号和指定官方账号绑定
    public function bindUserByThird($user, array $thirdUser)
    {
        if (! isset($thirdUser['id']) || ! $thirdUser['id']) {
            throw new \Exception('Invalid ThirdId');
        }

        $thirdUid = $thirdUser['id'];
        $platform = $this->getPlatform();

        // 该官方账号是否已绑定其他社区账号
        $bindRelation = AdminUserThirdPfBind::where([
            'user_id'  => $user->id,
            'platform' => $platform,
        ])->first();

        if ($bindRelation) {

            // 绑定关系已存在
            if ($bindRelation['third_user_id'] == $thirdUid) {
                return true;
            }

            // 自动解绑旧关系
            else {
                $bindRelation->delete();
            }
        }

        // 该社区账号是否已绑定其他官方账号
        $bindRelation = AdminUserThirdPfBind::where([
            'platform'      => $platform,
            'third_user_id' => $thirdUid,
        ])->first();

        // 自动解绑旧关系
        if ($bindRelation) {

            // 绑定关系已存在
            if ($bindRelation['user_id'] == $user->id) {
                return true;
            }

            // 自动解绑旧关系
            else {
                $bindRelation->delete();
            }
        }

        // 创建绑定关系
        AdminUserThirdPfBind::create([
            'user_id'       => $user->id,
            'platform'      => $platform,
            'third_user_id' => $thirdUid,
        ]);

        // 同步更新用户信息
        $user->update([
            'name'     => $thirdUser['full_info']['name'],
            'avatar'   => $thirdUser['full_info']['avatar_url'] ?? '',
        ]);

        return true;
    }
}
