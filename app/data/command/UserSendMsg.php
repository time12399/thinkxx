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

namespace app\data\command;

use app\data\model\DataUser;
use app\data\service\UserBalanceService;
use app\data\service\UserRebateService;
use think\admin\Command;
use think\admin\Exception;
use think\console\Input;
use think\console\Output;

use app\axapi\controller\api\Goods;


/**
 * 用户余额及返利重算处理
 * Class UserBalance
 * @package app\data\command
 */
class UserSendMsg extends Command
{
    protected function configure()
    {
        $this->setName('xdata:UserSendMsg');
        $this->setDescription('批量重新计算余额返利');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $g = new Goods($this->app);
        $g->sendMemberMsg();
        $this->setQueueSuccess(date('Y-m-d H:i:s')."发送数据");
    }
}