<?php
/**
 * Created by PhpStorm.
 * User: moTzxx
 * Date: 2020/10/18
 * Time: 18:13
 */

namespace app\common\model;


use think\Db;

class Xchats extends BaseModel
{
    /**
     * 聊天记录保存
     * @param null $postData
     * @return array
     */
    public function saveMessage($postData = null){
        $addData = $postData;
        $addData['is_read'] = 0;
        if ($postData['type'] == 'say'){
            $addData['type'] = 1;
        }else{
            $addData['type'] = 2;
        }
        $status = Db::name('xchat_logs')->insert($addData);

        $message = $status ? "聊天记录保存成功" : "聊天记录保存失败！";
        return ['status' => $status,'message' => $message];
    }


    /**
     * 获取管理员信息
     * @param int $admin_id
     * @return array|null|\PDOStatement|string|\think\Model
     */
    public function getAdminUserInfo($admin_id = 0){
        $userInfo = Db::name('xadmins')
            ->field('user_name,picture')
            ->where('id',$admin_id)
            ->find();
        if ($userInfo){
            $userInfo['picture'] = imgToServerView($userInfo['picture']);
        }
        return $userInfo;
    }

    /**
     * 获取对话人称呼
     * @param int $to_id
     * @return mixed
     */
    public function getAdminName($to_id = 0){
        $toName = Db::name('xadmins')->where('id',$to_id)->value('user_name');
        return $toName;
    }

    /**
     * 加载聊天记录
     * @param array $postData
     * @return array|\PDOStatement|string|\think\Collection|void
     */
    public function loadMessage($postData = []){
        $from_id = $postData['from_id'];
        $to_id = $postData['to_id'];

        $where = [['from_id','<>','to_id'],['from_id|to_id','=',$from_id],['from_id|to_id','=',$to_id]];
        $logCount = Db::name('xchat_logs')
            ->where($where)
            ->count('id');
        if($logCount >= 10){
            $logRes =  Db::name('xchat_logs')
                ->where($where)
                ->limit($logCount-10,10)
                ->order('id','ase')
                ->select();
        }else{
            $logRes =   Db::name('xchat_logs')
                ->where($where)
                ->order('id','ase')
                ->select();
        }
        $from_head = $this->getAdminUserInfo($from_id)['picture'];
        $to_head = $this->getAdminUserInfo($to_id)['picture'];
        return ['from_head' => $from_head,'to_head' => $to_head ,'logRes' => $logRes];
    }

    public function changeNoRead($postData = []){
        $from_id = $postData['from_id'];
        $to_id = $postData['to_id'];
        //进行未读消息的更新
        $this->readFromOthersLogs($from_id,$to_id);
    }

    /**
     * 获取本人没有读的消息数量
     * @param int $curr_id
     * @return float|string
     */
    public function getNoReadCount($curr_id = 0){
        $count = Db::name('xchat_logs')
            ->where([['to_id','=',$curr_id],['is_read','=',0]])
            ->count('id');
        return $count>=99 ? '99+':$count;
    }
    /**
     * 更新未读消息
     * @param int $curr_id
     * @param int $other_id
     */
    public function readFromOthersLogs($curr_id = 0,$other_id = 0){
        Db::name('xchat_logs')
            ->where([['to_id','=',$curr_id],['from_id','=',$other_id],['is_read','=',0]])
            ->update(['is_read' => 1]);
    }
    /**
     * 获取聊天列表数据
     * @param $curr_id
     * @return array
     */
    public function getChatList($curr_id){
        $chatList = Db::name('xchat_logs cl')
            ->field('from_id,to_id,log_time')
            ->join('xadmins a','a.id = cl.from_id')
            ->where([['to_id|from_id','=',$curr_id],['a.status','=',1]])
            ->order('cl.id','desc')
            ->group('from_id')
            ->select();

        foreach ($chatList as $key => $value){
            $from_id = $value['from_id'];
            $to_id = $value['to_id'];
            $head_id = ($from_id == $curr_id) ? $to_id:$from_id;

            $chatList[$key]['head_url'] = $this->getAdminUserInfo($head_id)['picture'];
            $chatList[$key]['user_name'] = $this->getAdminUserInfo($head_id)['user_name'];
            $chatList[$key]['countNoRead'] = $this->getCountNoread($curr_id,$head_id);
            $chatList[$key]['last_message'] = $this->getLastMessage($curr_id,$head_id);
            $chatList[$key]['redi_url'] = url("/cms/chat/index/$head_id");
        }
        return $chatList;
    }

    /**
     * 根据 $curr_id 来获取发送的未读消息
     * @param $curr_id
     * @param $other_id
     * @return float|string
     */
    public function getCountNoRead($curr_id,$other_id){
        $noReadCount =  Db::name('xchat_logs')
            ->where(['from_id'=>$other_id,'to_id'=>$curr_id,'is_read'=>0])
            ->count('id');
        return $noReadCount >= 99?"99+":$noReadCount;
    }

    /**
     * 根据fromid和toid来获取他们聊天的最后一条数据
     * @param $from_id
     * @param $to_id
     * @return array|null|\PDOStatement|string|\think\Model
     */
    public function getLastMessage($from_id,$to_id){
        $where = [['from_id','<>','to_id'],['from_id|to_id','=',$from_id],['from_id|to_id','=',$to_id]];
        $info = Db::name('xchat_logs')->where($where)
            ->order('id DESC')
            ->limit(1)
            ->find();
        if ($info){
            if ($info['type'] == 2){
                $info['content'] = '【图片】';
            }
        }
        return $info;
    }

    /**
     * 获取管理人员列表
     * @param $curr_id
     * @return array|\PDOStatement|string|\think\Collection
     */
    public function getUserList($curr_id){
        $res = Db::name('xadmins')
            ->field('id,user_name,picture')
            ->where([['id','<>',$curr_id],['id','<>',0],['status','=',1]])
            ->order('id','asc')
            ->select();
        if ($res){
            foreach ($res as $key => $value){
                $admin_id = $value['id'];
                $res[$key]['picture'] = imgToServerView($value['picture']);
                $res[$key]['redi_url'] = url("/cms/chat/index/$admin_id");
            }
        }
        return $res;
    }
}