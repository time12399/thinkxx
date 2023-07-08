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
use app\axapi\model\DataUserTransfer;
use app\axapi\service\UserRebateService;
use app\axapi\service\UserTransferService;
use think\admin\extend\CodeExtend;



use think\facade\Db;
use Exception;
use think\exception\HttpResponseException;
use think\facade\Request;


/**
 * 用户提现接口
 * Class Transfer
 * @package app\axapi\controller\api\auth
 */
class Transfer extends Auth
{
    /**
     * 提交提现处理
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addWithdraw()
    {
        // 检查用户状态
        $this->checkUserStatus();
        // 接收输入数据
        $data = $this->_vali([
            'moeny_type.require'   => '提现方式不能为空！',
            'money_class_id.require' => '货币类型不能为空',
            'money_address_id.require' => '请选择提现地址',
            'money.require' => '提现金额不能为空！'
        ]);
        $remark = '';
        $r = Request::param();
        if(isset($r['money_remark'])){
            $remark = $r['money_remark'];
        }
        $data = [
            'uuid'=>$this->uuid,
            'type'=>$data['moeny_type'],
            'date'=>time(),
            'status'=>1,
            'amount'=>$data['money']
        ];
        $data['code'] = CodeExtend::uniqidDate(20, 'T');
        $data['remark'] = $remark;
        $DataUserTransfer = new DataUserTransfer;
        // 提现表 data_user_transfer
        $my_total = Db::table('data_user')->where('id',$this->uuid)->value('my_total');
        if($my_total < $data['amount']){
            $this->error("可提现金额不足");
        }
        try {
            // 给用户扣钱
            // 新增提现订单
            $this->app->db->transaction(function () use ($DataUserTransfer,$data) {
                Db::table('data_user')->where('id',$this->uuid)->dec('my_total',$data['amount'])->update();
                $DataUserTransfer->save($data);
            });
            $this->success('操作成功',[]);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            $this->error("操作失败，{$exception->getMessage()}");
        }
    }

    /**
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 提现回显信息
     */
    public function showWithdraw()
    {
        $data = $this->_vali([
            'showWithdraw.require'   => '提现方式不能为空！',
        ]);
        if($data['showWithdraw'] == 1){
            $list = Db::table('base_user_payment')
                ->field('id,name')
                ->where([
                    'type'=>'withdraw_number',
                    'status'=>1,
                ])->order('sort desc')->order('id desc')->select();
            $myList = Db::table('base_user_payment_address')
                ->field('id,name,address')
                ->where('type','number')
                ->where('uuid',$this->uuid)
                ->select();

            $myOrder = DataUserTransfer::where('uuid',$this->uuid)
                ->where('type',1)
                ->field('amount,amount as dzAmount, type as unit,type as sxf,status,create_at as datetime,type as address,remark')
                ->paginate(config('page')['page']);

            $this->success('操作成功',['withdraw_type'=>$list,'myList'=>$myList,'myOrder'=>$myOrder]);
        }else
        if($data['showWithdraw'] == 2){
            $list = Db::table('base_user_payment')
                ->field('id,name')
                ->where([
                    'type'=>'withdraw_card',
                    'status'=>1,
                ])->order('sort desc')->order('id desc')->select();
            $myList = Db::table('base_user_payment_address')
                ->field('id,name,address')
                ->where('type','card')
                ->where('uuid',$this->uuid)
                ->select();

            $myOrder = DataUserTransfer::where('uuid',$this->uuid)
                ->where('type',2)
                ->field('amount,amount as dzAmount, type as unit,type as sxf,status,create_at as datetime,type as address,remark')
                ->paginate(config('page')['page']);

            $this->success('操作成功',['withdraw_type'=>$list,'myList'=>$myList]);
        }else{
            $data = $this->_vali([
                'showWithdraw1.require'   => '提现方式不能为空！',
            ]);
        }
    }
    public function add()
    {die;
        // 检查用户状态
        $this->checkUserStatus();
        // 接收输入数据
        $data = $this->_vali([
            'type.require'   => '提现方式不能为空！',
            'amount.require' => '提现金额不能为空！',
            'remark.default' => '用户提交提现申请！',
        ]);
        $state = UserTransferService::config('status');
        if (empty($state)) $this->error('提现还没有开启！');
        $transfers = UserTransferService::config('transfer');
        if (empty($transfers[$data['type']]['state'])) $this->error('提现方式已停用！');
        // 提现数据补充
        $data['uuid'] = $this->uuid;
        $data['date'] = date('Y-m-d');
        $data['code'] = CodeExtend::uniqidDate(20, 'T');
        // 提现状态处理
        if (empty($transfers[$data['type']]['state']['audit'])) {
            $data['status'] = 1;
            $data['audit_status'] = 0;
        } else {
            $data['status'] = 3;
            $data['audit_status'] = 1;
            $data['audit_remark'] = '提现免审核';
            $data['audit_datetime'] = date('Y-m-d H:i:s');
        }
        // 扣除手续费
        $chargeRate = floatval(UserTransferService::config('charge'));
        $data['charge_rate'] = $chargeRate;
        $data['charge_amount'] = $chargeRate * $data['amount'] / 100;
        // 检查可提现余额
        [$total, $count] = UserRebateService::amount($this->uuid);
        if ($total - $count < $data['amount']) $this->error('可提现余额不足！');
        // 提现方式处理
        if ($data['type'] == 'alipay_account') {
            $data = array_merge($data, $this->_vali([
                'alipay_user.require' => '开户姓名不能为空！',
                'alipay_code.require' => '支付账号不能为空！',
            ]));
        } elseif (in_array($data['type'], ['wechat_qrcode', 'alipay_qrcode'])) {
            $data = array_merge($data, $this->_vali([
                'qrcode.require' => '收款码不能为空！',
            ]));
        } elseif (in_array($data['type'], ['wechat_banks', 'transfer_banks'])) {
            $data = array_merge($data, $this->_vali([
                'bank_wseq.require' => '银行编号不能为空！',
                'bank_name.require' => '银行名称不能为空！',
                'bank_user.require' => '开户账号不能为空！',
                'bank_bran.require' => '银行分行不能为空！',
                'bank_code.require' => '银行卡号不能为空！',
            ]));
        } elseif ($data['type'] != 'wechat_wallet') {
            $this->error('转账方式不存在！');
        }
        // 当日提现次数限制
        $map = ['uuid' => $this->uuid, 'type' => $data['type'], 'date' => $data['date']];
        $count = DataUserTransfer::mk()->where($map)->count();
        if ($count >= $transfers[$data['type']]['dayNumber']) $this->error("当日提现次数受限");
        // 提现金额范围控制
        if ($transfers[$data['type']]['minAmount'] > $data['amount']) {
            $this->error("不能少于{$transfers[$data['type']]['minAmount']}元");
        }
        if ($transfers[$data['type']]['maxAmount'] < $data['amount']) {
            $this->error("不能大于{$transfers[$data['type']]['maxAmount']}元");
        }
        // 写入用户提现数据
        if (DataUserTransfer::mk()->insert($data) !== false) {
            UserRebateService::amount($this->uuid);
            $this->success('提现申请成功');
        } else {
            $this->error('提现申请失败');
        }
    }

    /**
     * 用户提现记录
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get()
    {
        $query = DataUserTransfer::mQuery()->where(['uuid' => $this->uuid]);
        $result = $query->like('date,code')->in('status')->order('id desc')->page(true, false, false, 10);
        // 统计历史数据
        $map = [['uuid', '=', $this->uuid], ['status', '>', 0]];
        [$total, $count, $locks] = UserRebateService::amount($this->uuid);
        $this->success('获取提现成功', array_merge($result, [
            'total' => [
                '锁定' => $locks,
                '可提' => $total - $count,
                '上月' => DataUserTransfer::mk()->where($map)->whereLike('date', date("Y-m-%", strtotime('last day of -1 month')))->sum('amount'),
                '本月' => DataUserTransfer::mk()->where($map)->whereLike('date', date("Y-m-%"))->sum('amount'),
                '全年' => DataUserTransfer::mk()->where($map)->whereLike('date', date("Y-%"))->sum('amount'),
            ],
        ]));
    }

    /**
     * 用户取消提现
     */
    public function cancel()
    {
        $data = $this->_vali(['uuid.value' => $this->uuid, 'code.require' => '单号不能为空！']);
        DataUserTransfer::mk()->where($data)->whereIn('status', [1, 2, 3])->update([
            'status' => 0, 'change_time' => date("Y-m-d H:i:s"), 'change_desc' => '用户主动取消提现',
        ]);
        UserRebateService::amount($this->uuid);
        $this->success('取消提现成功');
    }

    /**
     * 用户确认提现
     */
    public function confirm()
    {
        $data = $this->_vali(['uuid.value' => $this->uuid, 'code.require' => '单号不能为空！']);
        DataUserTransfer::mk()->where($data)->whereIn('status', [4])->update([
            'status' => 5, 'change_time' => date("Y-m-d H:i:s"), 'change_desc' => '用户主动确认收款',
        ]);
        UserRebateService::amount($this->uuid);
        $this->success('确认收款成功');
    }

    /**
     * 获取用户提现配置
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function config()
    {
        $data = UserTransferService::config();
        $data['banks'] = UserTransferService::instance()->banks();
        $this->success('获取用户提现配置', $data);
    }
}