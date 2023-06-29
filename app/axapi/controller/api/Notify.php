<?php

// +----------------------------------------------------------------------
// | Shop-Demo for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023 Anyon <zoujingli@qq.com>
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\axapi\controller\api;

use app\axapi\service\payment\AlipayPaymentService;
use app\axapi\service\payment\JoinpayPaymentService;
use app\axapi\service\payment\WechatPaymentService;
use think\admin\Controller;

/**
 * 异步通知处理
 * Class Notify
 * @package app\axapi\controller\api
 */
class Notify extends Controller
{
    /**
     * 微信支付通知
     * @param string $scene 支付场景
     * @param string $param 支付参数
     * @return string
     * @throws \think\admin\Exception
     */
    public function wxpay(string $scene = 'order', string $param = ''): string
    {
        if (strtolower($scene) === 'order') {
            return WechatPaymentService::instance($param)->notify();
        } else {
            return 'success';
        }
    }

    /**
     * 支付宝支付通知
     * @param string $scene 支付场景
     * @param string $param 支付参数
     * @return string
     * @throws \think\admin\Exception
     */
    public function alipay(string $scene = 'order', string $param = ''): string
    {
        if (strtolower($scene) === 'order') {
            return AlipayPaymentService::instance($param)->notify();
        } else {
            return 'success';
        }
    }

    /**
     * 汇聚支付通知
     * @param string $scene 支付场景
     * @param string $param 支付参数
     * @return string
     * @throws \think\admin\Exception
     */
    public function joinpay(string $scene = 'order', string $param = ''): string
    {
        if (strtolower($scene) === 'order') {
            return JoinpayPaymentService::instance($param)->notify();
        } else {
            return 'success';
        }
    }
}