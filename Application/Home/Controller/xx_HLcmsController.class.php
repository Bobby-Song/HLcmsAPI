<?php
namespace Home\Controller;

use Think\Controller;

class HLcmsController extends Controller
{
    /**
     * 手机端注册
     * @access public
     * @param string phone_null 用户手机号
     * @param string password 用户密码
     * @return mixed
     */
    public function phoneReg()
    {
        $phoner = M('phoner');
        $phoneNumber = I('post.phoneNumber','','htmlspecialchars');
        $phonePass = I('post.phonePass','','htmlspecialchars');
        $phoneRePass = I('post.phoneRePass','','htmlspecialchars');
        $phonePattern = '/^1[3-9]{1}[0-9]{9}$/';
        $passPattern = '/^[0-9a-zA-Z]{6,22}$/';       // 6-22位的密码（可包含：数字、大小写英文字母）
        if (!preg_match($phonePattern, $phoneNumber)) {
            $output = array('data' => null, 'info' => 'Input Error', 'code' => 405);
            die(json_encode($output));
        }
        if (!preg_match($passPattern, $phonePass)) {
            $output = array('data' => null, 'info' => 'Input Error', 'code' => 405);
            die(json_encode($output));
        }
        if (strcmp($phonePass, $phoneRePass) != 0) {
            $output = array('data' => null, 'info' => 'Input Error', 'code' => 405);
            die(json_encode($output));
        }

        $userCheck = $phoner->where("phone_number = '$phoneNumber'")->select();
        if (!empty($userCheck)) {
            $output = array('data' => null, 'info' => 'The cell phone number has been registered!', 'code' => 406);
            die(json_encode($output));
        }

        $p_salt = uniqid();
        $pwd = md5(md5($phonePass).$p_salt);
        $regTime = time();

        $data['phone_number'] = $phoneNumber;
        $data['password'] = $pwd;
        $data['p_salt'] = $p_salt;
        $data['regtime'] = $regTime;

        if ($phoner->create($data)) {
            $result = $phoner->add();
            if ($result) {
                $output = array('data' => $result, 'info' => 'Success!', 'code' => 200);
            } else {
                $output = array('data' => null, 'info' => 'Register Fail!', 'code' => 302);
            }
        } else {
            $output = array('data' => null, 'info' => 'Register Fail!', 'code' => 302);
        }

        echo json_encode($output);
    }

    /**
     * 手机端登录
     * @access public
     * @param string phoneNumber 用户名（用户手机号）
     * @param string phonePass 用户密码
     * @return mixed
     */
    public function login()
    {
       $phoner = M('phoner');

        $phoneNumber = I('request.phoneNumber','','htmlspecialchars');
        $phonePass = I('request.phonePass','','htmlspecialchars');

        $currentUser = $phoner->where("phone_number = '$phoneNumber'")->select();
        if (empty($currentUser)) {
            $output = array('data' => $phoneNumber, 'info' => 'This phone number is not exists!', 'code' => 407);
            die(json_encode($output));
        } else {
            $currentPass = md5(md5($phonePass).$currentUser[0]['p_salt']);
            if (strcmp($currentPass, $currentUser[0]['password']) != 0) {
                $output = array('data' => null, 'info' => 'Input Error', 'code' => 405);
                die(json_encode($output));
            } else {
                $arrUser['id'] = $currentUser[0]['id'];
                $arrUser['phone_number'] = $currentUser[0]['phone_number'];
                $arrUser['user_name'] = $currentUser[0]['user_name'];
                $arrUser['sex'] = $currentUser[0]['sex'] == 0 ? '女' : '男';
                $arrUser['area_id'] = $currentUser[0]['area_id'];
                $_SESSION['phone_user'] = $arrUser;
                $output = array('data' => $arrUser, 'info' => 'Success!', 'code' => 200);
                echo json_encode($output);
            }
        }
    }

    /**
     * 手机端退出登录
     * @return mixed
     */
    public function logout()
    {
        if (!empty($_SESSION['phone_user'])) {
            unset($_SESSION['phone_user']);
            $output = array('data' => null, 'info' => 'Success!', 'code' => 200);
        } else {
            $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
        }
        echo json_encode($output);
    }

    /**
     * 获取子分类 或者 文章索引
     * @access public
     * @param int id 当前分类id，默认为0
     * @return mixed
     */
    public function getNode()
    {
        $nodeID = I('get.nodeID', 0, 'htmlspecialchars');
        $pageNum = I('get.pageNum', 1, 'htmlspecialchars');
        $numPerPage = I('get.numPerPage', 5 , 'htmlspecialchars');
        if (!is_int($pageNum)) {
            $pageNum = (int)$pageNum;
        }
        $pageNum = abs($pageNum) > 1 ? abs($pageNum) : 1;


        if (!is_int($numPerPage)) {
            $numPerPage = (int)$numPerPage;
        }
        $numPerPage = abs($numPerPage) > 1 ? abs($numPerPage) : 20;

        $start = ($pageNum - 1)  * $numPerPage;

        $consults = M('content_title');
        $content = M('content');
        $users = M('user');

        if ($nodeID == 55) {    // 如果当前栏目ID为55（即：窗口测评），则遍历出所有的测评窗口
            $contentTitles = $consults->where("node_id = '$nodeID'")->select();
            if (empty($contentTitles)) {
                $output = array('data' => null, 'info' => 'This node has no contents!', 'code' => 401);
            } else {
                $output = array('data' => $contentTitles, 'info' => 'Success!', 'code' => 200);
            }
        } else {

            $sql = "select * from hl_content where status = 2 and node_id in (select id from hl_node where pid = ". $nodeID. " or id = ". $nodeID .") order by orders desc limit " . $start . "," . $numPerPage;
            $contents = $content->query($sql);
            $nodeContents = array();
            foreach ($contents as $key=>$value) {
                $name = $users->where("id = ".$value['release_id'])->getField('name');
                $nodeContents[$key]['id'] = $value['id'];
                $nodeContents[$key]['title'] = $value['title'];
                $nodeContents[$key]['title_pic'] = $value['title_pic'];
                if ($value['types'] == 6 || $value['types'] == 7) {
                    $nodeContents[$key]['video'] = $value['videos'];
                }
                /*$typeArray = array(
                    '1' => '全文排版',
                    '2' => '图文绕排',
                    '3' => '图文正排',
                    '4' => '相册',
                    '5' => '表格',
                    '6' => '视频点播',
                    '7' => '监控视频',
                    '10' => '征询类有图',
                    '11' => '征询类无图'
                );*/
                $nodeContents[$key]['types'] = ($value['node_id'] == 57 || $value['node_id'] == 104 || $value['node_id'] == 106) ? '11' : $value['types'];
                $nodeContents[$key]['release_name'] = $name;
                $nodeContents[$key]['release_time'] = $value['release_time'];
                $nodeContents[$key]['content'] = $value['types'] == 5 ? '' : $value['content'];
            }
            if (empty($nodeContents)) {
                $output = array('data' => null, 'info' => 'This node has no contents!', 'code' => 401);
            } else {
                $output = array('data' => $nodeContents, 'info' => 'Success!', 'code' => 200);
            }
        }

        echo json_encode($output);
    }

    /**
     * 获取每个文章的详情
     * @access public
     * @param int contentID 文章ID
     * @return mixed 成功时返回json，失败时返回错误码
     */
    public function getContents()
    {
        $contentID = $nodeID = I('get.contentID','','htmlspecialchars');    // 接受get传值contentID
        $pageContent = array();

        $user = M('user');
        $node = M('node');
        $content = M('content');
        $extension = M('content_extension');

        $contents = $content->where("id = '$contentID'")->find();
        $data0 = $node->where("id = ".$contents['node_id'])->find();

        $data1 = $node->field('name', 'pid')->where("id = ".$data0['pid'])->find();
        if ($data0[0]['style'] == 4) {
            $data2 = $node->field('name', 'pid')->where("id = ".$data1['pid'])->find();
            $pageContent['ppNodeName'] = $data2['name'];
        }

        $data_r = $node->field('name')->where("id = ".$contents['root_id'])->find();
        $pageContent['id'] = $contents['id'];
        $pageContent['node_id'] = $contents['node_id'];
        $pageContent['root_id'] = $contents['root_id'];
        $pageContent['nodeName'] = $data0['name'];
        $pageContent['pNodeName'] = $data1['name'];
        $pageContent['rootName'] = $data_r['name'];
        $pageContent['title_pic'] = $contents['title_pic'];
        $pageContent['release_time'] = $contents['release_time'];
        $pageContent['title'] = $contents['title'];

        $types = ($contents['node_id'] == 57 || $contents['node_id'] == 104 || $contents['node_id'] == 106) ? '11' : $contents['types'];

        $releaseName = $user->where("id = ".$contents['release_id'])->getField('name');
        $pageContent['releaseName'] = $releaseName;

        if ($types == 10 || $types == 11) {

            if ($types == 10) {
                $clickArray = array();
                if (strpos($contents['content'], '^^')) {    // 处理多条有图征询文字
                    $contentArr = explode('^^', $contents['content']);
                    for ($i = 0; $i < count($contentArr); $i ++) {
                        $textArr = explode('##', $contentArr[$i]);
                        $pageContent['content'][$i]['textTitle'] = $textArr[0];
                        $pageContent['content'][$i]['textContent'] = strtr(self::make_semiAngle($textArr[1]), array(' ' => '', '<br />' => ''));
                        $agreeNum = $extension->where("content_id = $contentID and status = 1 and arr_id = ".$i)->count();
                        $clickArray[$i]['agreeNum'] = $agreeNum;                     // 赞成数

                        $againstNum = $extension->where("content_id = $contentID and status = 2 and arr_id = ".$i)->count();
                        $clickArray[$i]['againstNum'] = $againstNum;                 // 反对数
                    }
                } else {
                    $textArr = explode('##', $contents['content']);
                    $pageContent['content'][0]['textTitle'] = $textArr[0];
                    $pageContent['content'][0]['textContent'] = strtr(self::make_semiAngle($textArr[1]), array(' ' => '', '<br />' => ''));
                    $agreeNum = $extension->where("content_id = $contentID and status = 1")->count();
                    $clickArray[0]['agreeNum'] = $agreeNum;                     // 赞成数

                    $againstNum = $extension->where("content_id = $contentID and status = 2")->count();
                    $clickArray[0]['againstNum'] = $againstNum;                 // 反对数
                }
                $pageContent['clickArray'] = $clickArray;

                if (strpos($contents['pic'], '^^')) {    // 处理多条有图征询图片
                    $picArr = explode('^^', $contents['pic']);
                    for ($i = 0; $i < count($picArr); $i ++) {
                        $pics[$i] = $picArr[$i];
                    }
                } else {
                    $pics[0] = $contents['pic'];
                }
                $pageContent['pic'] = $pics;
            } else {
                $agreeNum = $extension->where("content_id = $contentID and status = 1")->count();
                $clickArray['agreeNum'] = $agreeNum;                     // 赞成数
                $againstNum = $extension->where("content_id = $contentID and status = 2")->count();
                $clickArray['againstNum'] = $againstNum;                 // 反对数
                $pageContent['clickArray'] = $clickArray;

                $pageContent['content'] = strtr(self::make_semiAngle($contents['content']), array(' ' => '', '<br />' => ''));
            }
        } elseif ($types == 3 || $types == 4) {
            $textArr = explode('^^', trim($contents['content'], '^^'));
            $picArr = explode('^^', trim($contents['pic'], '^^'));
            $pageContent['content'] = $textArr;
            $pageContent['pic'] = $picArr;
        } elseif ($types == 6 || $types == 7) {
            /*if ($contents['types'] == 6) {
                $pageContent['video'] = $contents['videos'];
                $pageContent['content_url'] = $contents['content_url'];
            }
            $pageContent['content'] = $contents['content'];*/
        } else {
            if ($contents['node_id'] == 53 || $contents['node_id'] == 54) {
                $contentArr = explode('#', $contents['content']);
                $consultArr = explode('#', str_replace('^^', '#', $contents['content_consult']));
                $pageContent['writer'] = $consultArr[0];                // 写信人
                $pageContent['letter_time'] = $consultArr[2];           // 写信时间
                $pageContent['letter_content'] = $contentArr[0];        // 写信内容
                $pageContent['letter_no'] = $consultArr[3];             // 信件编号
                $pageContent['letter_department'] = $consultArr[1];     // 处理部门
                $pageContent['reply_content'] = $contentArr[1];         // 回复内容
            } else {
                $pageContent['content'] = $contents['content'];
            }
        }
        echo $pageContentJson = json_encode($pageContent);
    }

    /**
     * 获取每个文章的详情
     * @access public
     * @param int contentTitleID 测评窗口ID
     * @return mixed 成功时返回json，失败时返回错误码
     */
    public function getConsult()
    {
        $contentTitleID = I('get.contentTitleID',0,'htmlspecialchars');
        $content = M('content');
        $user = M('user');
        $extentsions = M('content_extension');

        $consults = array();
        $consult_1 = $extentsions->where("content_consult = '$contentTitleID' and status = 1")->count();
        $consult_2 = $extentsions->where("content_consult = '$contentTitleID' and status = 2")->count();
        $consult_3 = $extentsions->where("content_consult = '$contentTitleID' and status = 3")->count();
        $consult_4 = $extentsions->where("content_consult = '$contentTitleID' and status = 4")->count();
        $clickArray = array($consult_1, $consult_2, $consult_3, $consult_4);
        $consults['clickArray'] = $clickArray;

        $contents = $content->where("content_consult = '$contentTitleID' and status = 2")->select();

        if (!empty($contents)) {
            foreach ($contents as $key=>$value) {
                $consults['messages'][$key]['id'] = $value['id'];
                $consults['messages'][$key]['letter'] = $value['title'];
                $consults['messages'][$key]['content'] = $value['content'];
                $consults['messages'][$key]['release_time'] = $value['release_time'];
                $releaser = $user->where("id = ".$value['release_id'])->getField('name');
                $consults['messages'][$key]['release'] = $releaser;
            }
            $output = array('data' => $consults, 'info' => 'Success!', 'code' => 200);
        } else {
            $output = array('data' => null, 'info' => 'This node has no contents!', 'code' => 401);
        }

        echo json_encode($output);
    }

    /**
     * 投票功能
     * @access public
     * @param int contentID 文章的ID
     * @param int status 用户投票选择
     * @return mixed
     */
    public function vote()
    {
        if (empty($_SESSION['phone_user'])) {
            $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
            die(json_encode($output));
        }
        $consultID = I('get.consultID',0,'htmlspecialchars');     // 测评窗口ID，为“窗口测评”传值用
        $contentID = I('get.contentID',0,'htmlspecialchars');     // 投票文章ID
        $status = I('get.status',0,'htmlspecialchars');           // 投票状态
        $arr_id = I('get.arr_id',0,'htmlspecialchars');           // 有图征询的arr_id
        
        $consults = M('content_title');
        $extension = M('content_extension');
        $content = M('content');
        
        $nodeIDs = $consults->where("id = $consultID")->select();
        if (!empty($nodeIDs)) {
            $nodeID = $nodeIDs[0]['node_id'];
        }
        if ($nodeID == 55) {
            $where['content_consult'] = $consultID;
            $where['phoner_id'] = $_SESSION['phone_user']['id'];
            $windowArr = $extension->where($where)->select();
            if (empty($windowArr)) {
                $data['content_consult'] = $consultID;
                $data['arr_id'] = $_SESSION['phone_user']['area_id'];
                $data['status'] = $status;
                $data['phoner_id'] = $_SESSION['phone_user']['id'];
                $data['times'] = date('Y-m-d', time());
                
                if ($extension->create($data)) {
                    $insertID = $extension->add();
                    if ($insertID) {
                        $output = array('data' => $insertID, 'info' => 'Success!', 'code' => 200);
                    } else {
                        $output = array('data' => null, 'info' => 'Vote Fail!', 'code' => 304);
                    }
                } else {
                    $output = array('data' => null, 'info' => 'Vote Fail!', 'code' => 304);
                }
            } else {
                $output = array('data' => null, 'info' => 'Please do not repeat the vote!', 'code' => 303);
                die(json_encode($output));
            }
        }
        
        $contentConditions = $content->where("id = $contentID")->select();

        if ($contentConditions[0]['types'] == 11 || $contentConditions[0]['node_id'] == 57 || $contentConditions[0]['node_id'] == 104 || $contentConditions[0]['node_id'] == 106) {
            $where['content_id'] = $contentID;
            $where['phoner_id'] = $_SESSION['phone_user']['id'];
            $voting_record = $extension->where($where)->select();

            if (empty($voting_record)) {
                $dat['content_id'] = $contentID;
                $dat['arr_id'] = $_SESSION['phone_user']['area_id'];
                $dat['status'] = $status;
                $dat['phoner_id'] = $_SESSION['phone_user']['id'];
                $dat['times'] = date('Y-m-d', time());
                
                if ($extension->create($dat)) {
                    $insertID = $extension->add();
                    if ($insertID) {
                        $output = array('data' => $insertID, 'info' => 'Success!', 'code' => 200);
                    } else {
                        $output = array('data' => null, 'info' => 'Vote Fail!', 'code' => 304);
                    }
                } else {
                    $output = array('data' => null, 'info' => 'Vote Fail!', 'code' => 304);
                }
            } else {
                $output = array('data' => null, 'info' => 'Please do not repeat the vote!', 'code' => 303);
                die(json_encode($output));
            }
        } elseif ($contentConditions[0]['types'] == 10) {
            $where['content_id'] = $contentID;
            $where['arr_id'] = $arr_id;
            $where['phoner_id'] = $_SESSION['phone_user']['id'];
            $voting_record = $extension->where($where)->select();

            if (empty($voting_record)) {
                $dat['content_id'] = $contentID;
                $dat['arr_id'] = $_SESSION['phone_user']['area_id'];
                $dat['status'] = $status;
                $dat['phoner_id'] = $_SESSION['phone_user']['id'];
                $dat['times'] = date('Y-m-d', time());

                if ($extension->create($dat)) {
                    $insertID = $extension->add();
                    if ($insertID) {
                        $output = array('data' => $insertID, 'info' => 'Success!', 'code' => 200);
                    } else {
                        $output = array('data' => null, 'info' => 'Vote Fail!', 'code' => 304);
                    }
                } else {
                    $output = array('data' => null, 'info' => 'Vote Fail!', 'code' => 304);
                }
            } else {
                $output = array('data' => null, 'info' => 'Please do not repeat the vote!', 'code' => 303);
                die(json_encode($output));
            }
        }
        echo json_encode($output);
    }

    /**
     * 将一个字串中含有全角的数字字符、字母、空格或'%+-'等字符转换为相应半角字符
     *
     * @access public
     * @param string $str 待转换字串
     * @return string $str 处理后字串
     */
    static public function make_semiAngle($str)
    {
        $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
            'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
            'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
            'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
            'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
            'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z', '〔' => '[','／' => '/', '＼' => '\\',
            '〕' => ']', '〖' => '[', '〗' => ']', '．' => '.', '＊' => '*',
            '［' => '[', '］' => '］', '‖' => '|', '〃' => '"','　' => ' ',
            '｛' => '{', '｝' => '}','＜' => '<', '＞' => '>', '％' => '%',
            '＋' => '+', '?' => '-', '－' => '-', '～' => '~', '…' => '-',
            '｜' => '|', '＝' => '=', '＾' => '^', '＄' => '$', '＆' => '&',
            '｀' => '`', '＇' => '\'');
        return strtr($str, $arr);
    }
}