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

namespace app\axapi\controller\api\auth;

use app\axapi\controller\api\Auth;
use app\axapi\model\DataUserBalance;
use think\admin\helper\QueryHelper;

use think\admin\extend\CodeExtend;

/**
 * 用户余额转账
 * Class Balance
 * @package app\axapi\controller\api\auth
 */
class Balance extends Auth
{

    /**
     * 充值接口
     */
    public function addRecharge()
    {
        $data = $this->_vali([
            'amount.require' => lang('notamount'),
            'network.require' => lang('notnetwork'),
        ]);

        $DataUserBalance = new DataUserBalance;
        $code = CodeExtend::uniqidDate('20', 'USER');
        $m = $DataUserBalance->save([
            "status"=>2,
            "uuid"=>$this->uuid,
            "name"=>'用户提交充值',
            "code"=>$code,
            "amount"=>$data['amount'],
            "network"=>$data['network']
        ]);
        if($m){
            $this->success('操作成功');
        }else{
            $this->error('操作失败','{}',0);
        }
    }


    /**
     * 获取用户余额记录
     */
    public function get()
    {
        DataUserBalance::mQuery(null, function (QueryHelper $query) {
            $query->withoutField('deleted,create_by');
            $query->where(['uuid' => $this->uuid, 'deleted' => 0])->like('create_at#date');
            $this->success('获取数据成功', $query->order('id desc')->page(true, false, false, 10));
        });
    }
}