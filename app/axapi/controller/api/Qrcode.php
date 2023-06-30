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

use think\admin\Controller;
use think\admin\model\SystemBase;
use app\phpqrcode\QRcode as Qr;

/**
 * 基础数据接口
 * Class Data
 * @package app\axapi\controller\api
 */
class Qrcode extends Controller
{

    /**
     * 获取指定数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getDictImg()
    {
        $value = 'asdasd';                    //二维码内容
        $errorCorrectionLevel = 'L';    //容错级别 
        $matrixPointSize = 5;            //生成图片大小  
        //生成二维码图片
        $QR = Qr::png($value,false,$errorCorrectionLevel, $matrixPointSize, 2);
        $base64Str = base64_encode($QR);
        echo $base64Str;
        die;
    }
}