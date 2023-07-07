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

namespace app\data\controller\user;

use app\data\model\BaseUserUpgrade;
use app\data\model\DataUser;
use app\data\model\DataUserBalance;
use app\data\service\UserAdminService;
use app\data\service\UserBalanceService;
use app\data\service\UserUpgradeService;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\model\SystemUser;
use think\admin\service\AdminService;

use think\admin\model\SystemBase;



use think\exception\HttpResponseException;
use Exception;
use think\facade\Db;

/**
 * 余额充值记录
 * Class Balance
 * @package app\data\controller\user
 */
class Balance extends Controller
{
    /**
     * 余额充值管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->title = '余额充值记录';
        // 统计用户余额
        $this->balance = UserBalanceService::amount(0);
        // 现有余额类型
        $this->names = DataUserBalance::mk()->group('name')->column('name');
        // 创建查询对象
        $query = DataUserBalance::mQuery()->equal('name,upgrade');
        // 用户搜索查询
        $db = DataUser::mQuery()->like('phone|nickname#user_keys')->db();
        if ($db->getOptions('where')) $query->whereRaw("uuid in {$db->field('id')->buildSql()}");
        // 数据查询分页

        $sql ='SELECT status as asd , SUM(amount) AS total_amount 
                FROM data_user_balance
                GROUP BY asd;
        ';
        $balance_list = Db::query($sql);
        $balance_listok = 0;
        $balance_listis = 0;
        $balance_listno = 0;
        foreach($balance_list as $v){
            if($v['asd'] == 1) $balance_listok = $v['total_amount']; 
            if($v['asd'] == 2) $balance_listis = $v['total_amount']; 
            if($v['asd'] == 3) $balance_listno = $v['total_amount']; 
        }
        
        $networkArr = SystemBase::where('type','recharge_pay')->select();
        $this->assign('networkArr',$networkArr);
        $this->assign([
            'balance_listok' => $balance_listok,
            'balance_listis' => $balance_listis,
            'balance_listno' => $balance_listno,
        ]);

        $query->where(['deleted' => 0])->like('code,remark')->dateBetween('create_at')->order('id desc')->page();
    }
    public function reject()
    {
        $data = $this->_vali([
            'id.require' => 'id不能为空',
            'status.require' => 'status_error'
        ]);
        $this->assign('data',$data);
        return $this->fetch();
    }
    public function state(){
        $data = $this->_vali([
            'id.require' => 'id不能为空',
            'status.require' => 'status_error'
        ]);

        if($data['status'] == 1)
        {
            $DataUserBalance = DataUserBalance::where('status',2)->find($data['id']);
            if (empty($DataUserBalance)) $this->error('请稍等，订单操作中');

            $this->user = DataUser::mk()->where(['id' => $DataUserBalance['uuid']])->find();
            if (empty($this->user)) $this->error('待充值的用户不存在！');
            
            $amount = $this->user['balance_total']+$DataUserBalance['amount'];
            try {
                // 给用户加钱
                // 修改订单状态
                $this->app->db->transaction(function () use ($DataUserBalance,$amount) {
                    DataUser::where('id',$DataUserBalance['uuid'])->update(['balance_total'=>$amount]);
                    DataUserBalance::where('id',$DataUserBalance['id'])->update([
                        'status'=>1
                    ]);
                });
                $this->success('操作成功',[]);
            } catch (HttpResponseException $exception) {
                throw $exception;
            } catch (Exception $exception) {
                $this->error("操作失败，{$exception->getMessage()}");
            }
        }
        //驳回
        if($data['status'] == 3)
        {
            $data = $this->_vali([
                'id.require' => 'id不能为空',
                'status.require' => 'status_error',
                'reason.require' => '请填写理由'
            ]);
            $a = DataUserBalance::where('id',$data['id'])->update([
                'status'=>3,
                'reason'=>$data['reason']
            ]);
            $this->success('操作成功',[]);
        }
        $this->error("操作失败，{$exception->getMessage()}");
    }

    /**
     * 数据列表处理
     * @param array $data
     */
    protected function _index_page_filter(array &$data)
    {
        UserAdminService::buildByUid($data);
        $uids = array_unique(array_column($data, 'create_by'));
        $users = SystemUser::mk()->whereIn('id', $uids)->column('username', 'id');
        $this->upgrades = BaseUserUpgrade::items();
        foreach ($data as &$vo) {
            $vo['upgradeinfo'] = $this->upgrades[$vo['upgrade']] ?? [];
            $vo['create_byname'] = $users[$vo['create_by']] ?? '';
        }
    }

    /**
     * 添加余额充值
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add()
    {
        $data = $this->_vali(['uuid.require' => '用户UID不能为空！']);
        $this->user = DataUser::mk()->where(['id' => $data['uuid']])->find();
        if (empty($this->user)) $this->error('待充值的用户不存在！');
        $networkArr = SystemBase::where('type','recharge_pay')->select();
        $this->assign('networkArr',$networkArr);
        DataUserBalance::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $data
     */
    protected function _form_filter(array &$data)
    {
        if (empty($data['code'])) {
            $data['code'] = CodeExtend::uniqidDate('20', 'B');
        }
        if ($this->request->isGet()) {
            $this->upgrades = BaseUserUpgrade::items();
        }
        if ($this->request->isPost()) {
            $data['create_by'] = AdminService::getUserId();
            if (empty(floatval($data['amount'])) && empty($data['upgrade'])) {
                $this->error('金额为零并且没有升级行为！');
            }
        }
    }

    /**
     * 表单结果处理
     * @param bool $state
     * @param array $data
     * @throws \think\db\exception\DbException
     */
    protected function _form_result(bool $state, array $data)
    {
        if ($state && isset($data['uuid'])) {
            UserBalanceService::amount($data['uuid']);
            UserUpgradeService::upgrade($data['uuid']);
        }
    }

    /**
     * 删除充值记录
     * @auth true
     */
    public function remove()
    {
        DataUserBalance::mDelete('', [['code', 'like', 'B%']]);
    }

    /**
     * 删除结果处理
     * @param bool $state
     * @throws \think\db\exception\DbException
     */
    protected function _delete_result(bool $state)
    {
        if ($state) {
            $map = [['id', 'in', str2arr(input('id', ''))]];
            foreach (DataUserBalance::mk()->where($map)->cursor() as $vo) {
                UserBalanceService::amount($vo['uuid']);
                UserUpgradeService::upgrade($vo['uuid']);
            }
        }
    }
}