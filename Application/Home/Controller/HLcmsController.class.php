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
        $phoneNumber = I('request.phoneNumber','','htmlspecialchars');
        $phonePass = I('request.phonePass','','htmlspecialchars');
        $phoneRePass = I('request.phoneRePass','','htmlspecialchars');
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
     * 获取首页内容
     * @return mixed
     */
    public function getIndex()
    {
        if (empty($_SESSION['phone_user'])) {
            $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
            die(json_encode($output));
        }

        $area_id = $_SESSION['phone_user']['area_id'];

        if (!$area_id) {
            $output = array('data' => null, 'info' => '请选择所属区域！', 'code' => 230);
            die(json_encode($output));
        }

        $node = M('node');
        $area = M('area');
        $content = M('content');

        $index_arr = [];

        // 获取首页栏目
        $index_node = $node->where(array('style' => 1))->field(array('id', 'name'))->select();
        foreach ($index_node as $key => $value) {
            if ($value['name'] == '首页') {
                $index_node[$key]['name'] = '贺兰TV';
            } elseif ($value['name'] == '智慧社区') {
                $index_node[$key]['name'] = '我的社区';
            }

            if (in_array($value['id'], [2, 3, 5, 6, 92])) {
                $child_arr = $node->where(array('pid' => $value['id']))->field(array('id', 'name'))->select();
                $index_node[$key]['child'] = $child_arr;
            } elseif ($value['id'] == 4) {
                $area_name = $area->where(array('id' => $area_id))->getField('name');

                $map['id'] = array('neq', $area_id);
                $map['type'] = array('eq', 2);
                $other_area = $area->where($map)->field(array('id', 'name'))->select();
                array_unshift($other_area, array('id' => $area_id, 'name' => $area_name));
                $index_node[$key]['child'] = $other_area;
            } elseif ($value['id'] == 1) {
                $index_node[$key]['child'] = [];
            }
        }
        $index_arr['index_menu'] = $index_node;

        // 获取首页广告
        $index_ads = $content->where(array('types' => 35, 'status' => 2))->order('id desc')->limit(1)->field(array('id', 'pic', 'videos'))->find();
        if (!empty($index_ads)) {
            $index_arr['index_ads']['img'] = SITE_URL . 'nx_heLan/image/homeImg/' . $index_ads['pic'];
            $index_arr['index_ads']['videos'] = SITE_URL . 'movies/' . $index_ads['videos'];
        } else {
            $index_arr['index_ads']['img'] = SITE_URL . 'nx_heLan/public/img/index_4.jpg';
            $index_arr['index_ads']['videos'] = '';
        }

        // 获取首页通知公告
        $index_notice = $content->where(array('types' => 37, 'status' => 2))->order('id desc')->limit(1)->getField('title');
        $index_arr['index_notice'] = $index_notice != '' ? $index_notice : '暂无通知公告！';

        // 获取首页滚动字幕
        $index_scroll = $content->where(array('types' => 33, 'status' => 2))->order('id desc')->limit(1)->getField('title');
        $index_arr['index_scroll'] = $index_scroll != '' ? $index_scroll : '贺兰欢迎您！';

        $output = array('data' => $index_arr, 'info' => 'Success!', 'code' => 200);
        echo json_encode($output);
    }

    /**
     * 获取用户信息
     * @param
     * @return mixed
     */
    public function getUserInfo()
    {
        if (empty($_SESSION['phone_user'])) {
            $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
            die(json_encode($output));
        }

        $main_arr = [];

        $phone_user = M('phoner')->where(array('id' => $_SESSION['phone_user']['id']))->field(array('phone_number', 'name', 'user_name', 'sex', 'area_id', 'address', 'regtime'))->find();
        $main_arr['phone_number'] = $phone_user['phone_number'];
        $main_arr['name'] = $phone_user['name'];
        $main_arr['user_name'] = $phone_user['user_name'];
        $main_arr['sex'] = $phone_user['sex'] == 0 ? '女' : '男';
        $main_arr['area_name'] = M('area')->where(array('id' => $phone_user['area_id']))->getField('name');
        $main_arr['address'] = $phone_user['address'];
        $main_arr['reg_time'] = date('Y-m-d', $phone_user['regtime']);

        $output = array('data' => $main_arr, 'info' => 'Success!', 'code' => 200);
        echo json_encode($output);
    }

    /**
     * 设置用户信息
     * @param user_name string 用户名
     * @param name string 用户姓名
     * @param sex int 用户性别 0-女 1-男 默认：0
     * @param area_id int 用户所选社区id
     * @param address string 用户地址
     * @return mixed
     */
    public function setUserInfo()
    {
        if (empty($_SESSION['phone_user'])) {
            $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
            die(json_encode($output));
        }

        $data['user_name'] = I('request.user_name', '');
        $data['name'] = I('request.name', '');
        $data['sex'] = I('request.sex', 0);
        $data['area_id'] = I('request.area_id', 0);
        $data['address'] = I('request.address', '');

        $ret = M('phoner')->where(array('phone_number' => $_SESSION['phone_user']['phone_number']))->save($data);
        if ($ret === false) {
            $output = array('data' => null, 'info' => 'Operate Fail!', 'code' => 304);
        } else {
            $_SESSION['phone_user']['area_id'] = $data['area_id'];
            $output = array('data' => null, 'info' => 'Success!', 'code' => 200);
        }
        echo json_encode($output);
    }

    /**
     * 展示所有社区
     */
    public function getAreas()
    {
        $area = M('area');
        $area_arr = $area->where(array('type' => 2))->field(array('id', 'name'))->select();
        if (!empty($area_arr)) {
            $output = array('data' => $area_arr, 'info' => 'Success!', 'code' => 200);
        } else {
            $output = array('data' => null, 'info' => '系统暂未添加社区!', 'code' => 401);
        }

        echo json_encode($output);
    }

    /**
     * 获取栏目的子分类
     * @access public
     * @param nodeID int 当前分类id，默认为0
     * @return mixed
     */
    public function getSonNode()
    {
        $nodeID = I('request.nodeID', 0);
        $node = M('node');

        $node_arr = $node->where(array('pid' => $nodeID))->field(array('id', 'name'))->select();

        if (!empty($node_arr)) {
            $output = array('data' => $node_arr, 'info' => 'Success!', 'code' => 200);
        } else {
            $output = array('data' => null, 'info' => 'Get data fail!', 'code' => 401);
        }

        echo json_encode($output);
    }

    /**
     * 获取栏目的子分类 或者 文章索引
     * @access public
     * @param nodeID int 当前分类id，默认为0
     * @param area_id int 用户选择的社区id，默认为空（“我的社区”栏目中专用）
     * @return mixed
     */
    public function getNode()
    {
        $nodeID = I('request.nodeID', 0);

        $node = M('node');
        $content = M('content');

        $main_arr = [];

        // 判断传入的nodeID是否属于社区
        $area_node_arr = range(40,74);
        array_unshift($area_node_arr, 4);   //社区所有栏目id集合

        // 如果请求的为社区栏目，则判断用户登录信息中是否有area_id
        if (in_array($nodeID, $area_node_arr)) {
            if (empty($_SESSION['phone_user'])) {
                $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
                die(json_encode($output));
            }
            //TODO 获取智慧社区内容
            $node_style = $node->where(array('id' => $nodeID))->getField('style');      // 判断栏目是第几层

            if ($node_style == 1) {     // 当为一级栏目时

                $area_selected_id = I('request.area_id', '');   //FIXME 用户所选择的区域id

                if ($area_selected_id) {
                    $area_id = $_SESSION['phone_user']['selected_area_id'] = $area_selected_id;
                } else {
                    if ($_SESSION['phone_user']['selected_area_id']) {
                        $area_id = $_SESSION['phone_user']['selected_area_id'];
                    } else {
                        $area_id = $_SESSION['phone_user']['selected_area_id'] = $_SESSION['phone_user']['area_id'];
                    }
                }

                // 获取社区名称
                $main_arr['area_id'] = $area_id;
                $main_arr['area_name'] = M('area')->where(array('id' => $area_id))->getField('name');

                // 获取社区首页展示图
                $area_pic = M('content')->where(array('area_id' => $area_id, 'types' => 150, 'status' => 2))->order('id desc')->limit(1)->getField('pic');
                $main_arr['area_pic'] = $area_pic != '' ? SITE_URL . 'nx_heLan/image/' . $area_pic : SITE_URL . 'nx_heLan/public_area/img/index_pic.png';

                // 获取社区首页滚动字幕
                $area_scroll = M('content')->where(array('area_id' => $area_id, 'types' => 51, 'status' => 2))->order('id desc')->limit(1)->getField('content');
                $main_arr['area_scroll'] = isset($area_scroll) ? $area_scroll : M('area')->where(['id' => $area_id])->getField('name') . '欢迎您！';

                // 获取智慧社区二级栏目
                $second_node = $node->where(array('pid' => $nodeID))->field(array('id', 'name'))->select();
                $main_arr['area_node'] = $second_node;
            } elseif ($node_style == 2) {
                // 获取子栏目以及条目
                $node_info = $node->where(array('id' => $nodeID))->find();
                $main_arr['id'] = $nodeID;
                $main_arr['name'] = $node_info['name'];
                $main_arr['services'] = $node_info['services'];

                // 获取广告
                if ($node_info['has_ads'] == 1) {
                    $map['node_id'] = array('eq', $nodeID);
                    $map['status'] = array('eq', 2);
                    $map['types'] = array('exp', 'in(100, 101)');
                    $map['area_id'] = array('eq', $_SESSION['phone_user']['selected_area_id']);

                    $ads_arr = $content->where($map)->order('id desc')->limit(1)->field(array('id', 'title', 'pic', 'videos'))->find();
                    if (!empty($ads_arr)) {
                        $main_arr['ads_arr']['id'] = $ads_arr['id'];
                        $main_arr['ads_arr']['pic'] = SITE_URL . 'nx_heLan/image/' . $ads_arr['pic'];
                        $main_arr['ads_arr']['videos'] = SITE_URL . 'movies/' . $ads_arr['videos'];
                    } else {
                        $main_arr['ads_arr']['id'] = '';
                        $main_arr['ads_arr']['pic'] = SITE_URL . 'nx_heLan/public_area/img/0.jpg';
                        $main_arr['ads_arr']['videos'] = '';
                    }
                }

                if (!($node_info['has_top'] && $node_info['has_menu'])) {  // 若该二级栏目只有三级栏目，无四级栏目
                    $third_node = $node->where(array('pid' => $nodeID))->select();
                    foreach ($third_node as $key => $value) {
                        $main_arr['child'][$key]['id'] = $value['id'];
                        $main_arr['child'][$key]['name'] = $value['name'];
                        $main_arr['child'][$key]['services'] = $value['services'];

                        $com['node_id'] = array('eq', $value['id']);
                        $com['status'] = array('eq', 2);
                        $com['types'] = array('exp', ' not in(100, 101) ');
                        $com['area_id'] = array('eq', $_SESSION['phone_user']['selected_area_id']);
                        $content_arr = $content->where($com)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time', 'node_id'))->select();

                        if (!empty($content_arr)) {
                            foreach ($content_arr as $content_key => $content_value) {
                                $content_arr[$content_key]['title_pic'] = $content_value['title_pic'] == '' ? SITE_URL . 'nx_heLan/public_area/img/no.png' : SITE_URL . 'nx_heLan/image/' . $content_value['title_pic'];
                                $content_arr[$content_key]['types'] = in_array($content_value['node_id'], [47, 48]) ? '11' : $content_value['types'];
                                $content_arr[$content_key]['videos'] = $content_value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $content_value['id']))->getField('videos') : '';

                                $content_arr[$content_key]['has_vote'] = in_array($content_value['node_id'], [47, 48]) ? '1' : '0';
                                $content_arr[$content_key]['has_buy'] = in_array($content_value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                                $content_arr[$content_key]['has_join'] = in_array($content_value['node_id'], [49, 50]) ? '1' : '0';
                                $content_arr[$content_key]['has_date'] = in_array($content_value['node_id'], [79]) ? '1' : '0';
                            }
                            $main_arr['child'][$key]['child'] = $content_arr;
                        } else {
                            $main_arr['child'][$key]['child'][0]['id'] = '';
                            $main_arr['child'][$key]['child'][0]['title'] = '暂无内容';
                            $main_arr['child'][$key]['child'][0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
                            $main_arr['child'][$key]['child'][0]['types'] = '';
                            $main_arr['child'][$key]['child'][0]['release_time'] = '';
                            $main_arr['child'][$key]['child'][0]['has_vote'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_buy'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_join'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_date'] = '0';
                        }
                    }
                } elseif ($node_info['has_top'] && $node_info['has_menu']) {    // 有四级栏目时
                    $third_node = $node->where(array('pid' => $nodeID))->select();
                    foreach ($third_node as $key => $value) {
                        $main_arr['child'][$key]['id'] = $value['id'];
                        $main_arr['child'][$key]['name'] = $value['name'];
                        $main_arr['child'][$key]['services'] = $value['services'];

                        $fourth_node = $node->where(array('pid' => $value['id']))->field('id')->select();
                        $fourth_node_str = '';
                        foreach ($fourth_node as $k => $v) {
                            $fourth_node_str .= $v['id'] . ',';
                        }
                        $fourth_node_str = rtrim($fourth_node_str, ',');

                        $com['node_id'] = array('in', $fourth_node_str);
                        $com['status'] = array('eq', 2);
                        $com['types'] = array('exp', ' not in(100, 101) ');
                        $com['area_id'] = array('eq', $_SESSION['phone_user']['selected_area_id']);
                        $content_arr = $content->where($com)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time', 'node_id'))->select();

                        if (!empty($content_arr)) {
                            foreach ($content_arr as $content_key => $content_value) {
                                $content_arr[$content_key]['title_pic'] = $content_value['title_pic'] == '' ? SITE_URL . 'nx_heLan/public_area/img/no.png' : SITE_URL . 'nx_heLan/image/' . $content_value['title_pic'];
                                $content_arr[$content_key]['types'] = in_array($content_value['node_id'], [47, 48]) ? '11' : $content_value['types'];
                                $content_arr[$content_key]['videos'] = $content_value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $content_value['id']))->getField('videos') : '';
                                $content_arr[$content_key]['has_vote'] = in_array($content_value['node_id'], [47, 48]) ? '1' : '0';
                                $content_arr[$content_key]['has_buy'] = in_array($content_value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                                $content_arr[$content_key]['has_join'] = in_array($content_value['node_id'], [49, 50]) ? '1' : '0';
                                $content_arr[$content_key]['has_date'] = in_array($content_value['node_id'], [79]) ? '1' : '0';
                            }
                            $main_arr['child'][$key]['child'] = $content_arr;
                        } else {
                            $main_arr['child'][$key]['child'][0]['id'] = '';
                            $main_arr['child'][$key]['child'][0]['title'] = '暂无内容';
                            $main_arr['child'][$key]['child'][0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
                            $main_arr['child'][$key]['child'][0]['types'] = '';
                            $main_arr['child'][$key]['child'][0]['release_time'] = '';
                            $main_arr['child'][$key]['child'][0]['has_vote'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_buy'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_join'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_date'] = '0';
                        }
                    }
                }
            } elseif ($node_style == 3) {   // 三级栏目
                // 获取子栏目以及条目
                $fourth_node = $node->where(array('pid' => $nodeID))->field('id')->select();
                $fourth_node_str = '';
                foreach ($fourth_node as $k => $v) {
                    $fourth_node_str .= $v['id'] . ',';
                }
                $fourth_node_str = $fourth_node_str . $nodeID;

                $com['node_id'] = array('in', $fourth_node_str);
                $com['status'] = array('eq', 2);
                $com['types'] = array('exp', ' not in(100, 101) ');
                $com['area_id'] = array('eq', $_SESSION['phone_user']['selected_area_id']);

                $content_arr = $content->where($com)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time', 'node_id'))->select();
                if (!empty($content_arr)) {
                    foreach ($content_arr as $content_key => $content_value) {
                        $content_arr[$content_key]['title_pic'] = $content_value['title_pic'] == '' ? SITE_URL . 'nx_heLan/public_area/img/no.png' : SITE_URL . 'nx_heLan/image/' . $content_value['title_pic'];
                        $content_arr[$content_key]['types'] = in_array($content_value['node_id'], [47, 48]) ? '11' : $content_value['types'];
                        $content_arr[$content_key]['videos'] = $content_value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $content_value['id']))->getField('videos') : '';
                        $content_arr[$content_key]['has_vote'] = in_array($content_value['node_id'], [47, 48]) ? '1' : '0';
                        $content_arr[$content_key]['has_buy'] = in_array($content_value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                        $content_arr[$content_key]['has_join'] = in_array($content_value['node_id'], [49, 50]) ? '1' : '0';
                        $content_arr[$content_key]['has_date'] = in_array($content_value['node_id'], [79]) ? '1' : '0';
                    }
                    $main_arr = $content_arr;
                } else {
                    $main_arr[0]['id'] = '';
                    $main_arr[0]['title'] = '暂无内容';
                    $main_arr[0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
                    $main_arr[0]['types'] = '';
                    $main_arr[0]['release_time'] = '';
                    $main_arr[0]['has_vote'] = '0';
                    $main_arr[0]['has_buy'] = '0';
                    $main_arr[0]['has_join'] = '0';
                    $main_arr[0]['has_date'] = '0';
                }
            }

        } else {
            $node_style = $node->where(array('id' => $nodeID))->getField('style');      // 判断栏目是第几层

            if ($node_style == 1) {     // 一级栏目
                // 查找所有的二级栏目
                $main_arr = $node->where(array('pid' => $nodeID, 'style' => 2))->order('id asc')->select();

                if ($main_arr[0]['has_ads'] == 1) {     //若首个二级栏目有广告图，则查询
                    $map['node_id'] = array('eq', $main_arr[0]['id']);
                    $map['status'] = array('eq', 2);
                    $map['types'] = array('exp', 'in(100, 101)');
                    $ads_arr = $content->where($map)->order('id desc')->limit(1)->field(array('id', 'title', 'pic', 'videos'))->find();
                    if (!empty($ads_arr)) {
                        $main_arr[0]['ads_arr']['id'] = $ads_arr['id'];
                        $main_arr[0]['ads_arr']['pic'] = SITE_URL . 'nx_heLan/image/' . $ads_arr['pic'];
                        $main_arr[0]['ads_arr']['videos'] = SITE_URL . 'movies/' . $ads_arr['videos'];
                    } else {
                        $main_arr[0]['ads_arr']['id'] = '';
                        $main_arr[0]['ads_arr']['pic'] = SITE_URL . 'nx_heLan/public/img/0.jpg';
                        $main_arr[0]['ads_arr']['videos'] = '';
                    }
                }

                if (!($main_arr[0]['has_top'] && $main_arr[0]['has_menu'])) {  // 若首个二级栏目只有三级栏目，无四级栏目
                    $third_node = $node->where(array('pid' => $main_arr[0]['id']))->select();
                    foreach ($third_node as $key => $value) {
                        $main_arr[0]['child'][$key]['id'] = $value['id'];
                        $main_arr[0]['child'][$key]['name'] = $value['name'];
                        $main_arr[0]['child'][$key]['services'] = $value['services'];

                        $com['node_id'] = array('eq', $value['id']);
                        $com['status'] = array('eq', 2);
                        $com['types'] = array('exp', ' not in(100, 101) ');
                        $content_arr = $content->where($com)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time', 'node_id'))->select();

                        if (!empty($content_arr)) {
                            foreach ($content_arr as $content_key => $content_value) {
                                $content_arr[$content_key]['title_pic'] = $content_value['title_pic'] == '' ? SITE_URL . 'nx_heLan/public/img/no.png' : SITE_URL . 'nx_heLan/image/' . $content_value['title_pic'];
                                $content_arr[$content_key]['videos'] = $content_value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $content_value['id']))->getField('videos') : '';
                                $content_arr[$content_key]['has_vote'] = in_array($content_value['node_id'], [47, 48]) ? '1' : '0';
                                $content_arr[$content_key]['has_buy'] = in_array($content_value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                                $content_arr[$content_key]['has_join'] = in_array($content_value['node_id'], [49, 50]) ? '1' : '0';
                                $content_arr[$content_key]['has_date'] = in_array($content_value['node_id'], [79]) ? '1' : '0';
                            }
                            $main_arr[0]['child'][$key]['child'] = $content_arr;
                        } else {
                            $main_arr[0]['child'][$key]['child'][0]['id'] = '';
                            $main_arr[0]['child'][$key]['child'][0]['title'] = '暂无内容';
                            $main_arr[0]['child'][$key]['child'][0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
                            $main_arr[0]['child'][$key]['child'][0]['types'] = '';
                            $main_arr[0]['child'][$key]['child'][0]['release_time'] = '';
                            $main_arr[0]['child'][$key]['child'][0]['has_vote'] = '0';
                            $main_arr[0]['child'][$key]['child'][0]['has_buy'] = '0';
                            $main_arr[0]['child'][$key]['child'][0]['has_join'] = '0';
                            $main_arr[0]['child'][$key]['child'][0]['has_date'] = '0';

                        }
                    }
                }
            } elseif ($node_style == 2) {     // 二级栏目
                // 获取子栏目以及条目
                $node_info = $node->where(array('id' => $nodeID))->find();
                $main_arr['id'] = $nodeID;
                $main_arr['name'] = $node_info['name'];
                $main_arr['services'] = $node_info['services'];

                // 获取广告
                if ($node_info['has_ads'] == 1) {
                    $map['node_id'] = array('eq', $nodeID);
                    $map['status'] = array('eq', 2);
                    $map['types'] = array('exp', 'in(100, 101)');

                    $ads_arr = $content->where($map)->order('id desc')->limit(1)->field(array('id', 'title', 'pic', 'videos'))->find();
                    if (!empty($ads_arr)) {
                        $main_arr['ads_arr']['id'] = $ads_arr['id'];
                        $main_arr['ads_arr']['pic'] = SITE_URL . 'nx_heLan/image/' . $ads_arr['pic'];
                        $main_arr['ads_arr']['videos'] = SITE_URL . 'movies/' . $ads_arr['videos'];
                    } else {
                        $main_arr['ads_arr']['id'] = '';
                        $main_arr['ads_arr']['pic'] = SITE_URL . 'nx_heLan/public/img/0.jpg';
                        $main_arr['ads_arr']['videos'] = '';
                    }
                }

                if (!($node_info['has_top'] && $node_info['has_menu'])) {  // 若该二级栏目只有三级栏目，无四级栏目
                    $third_node = $node->where(array('pid' => $nodeID))->select();
                    foreach ($third_node as $key => $value) {
                        $main_arr['child'][$key]['id'] = $value['id'];
                        $main_arr['child'][$key]['name'] = $value['name'];
                        $main_arr['child'][$key]['services'] = $value['services'];

                        $com['node_id'] = array('eq', $value['id']);
                        $com['status'] = array('eq', 2);
                        $com['types'] = array('exp', ' not in(100, 101) ');
                        $content_arr = $content->where($com)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time', 'node_id'))->select();

                        if (!empty($content_arr)) {
                            foreach ($content_arr as $content_key => $content_value) {
                                $content_arr[$content_key]['title_pic'] = $content_value['title_pic'] == '' ? SITE_URL . 'nx_heLan/public/img/no.png' : SITE_URL . 'nx_heLan/image/' . $content_value['title_pic'];
                                $content_arr[$content_key]['videos'] = $content_value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $content_value['id']))->getField('videos') : '';
                                $content_arr[$content_key]['has_vote'] = in_array($content_value['node_id'], [47, 48]) ? '1' : '0';
                                $content_arr[$content_key]['has_buy'] = in_array($content_value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                                $content_arr[$content_key]['has_join'] = in_array($content_value['node_id'], [49, 50]) ? '1' : '0';
                                $content_arr[$content_key]['has_date'] = in_array($content_value['node_id'], [79]) ? '1' : '0';
                            }
                            $main_arr['child'][$key]['child'] = $content_arr;
                        } else {
                            $main_arr['child'][$key]['child'][0]['id'] = '';
                            $main_arr['child'][$key]['child'][0]['title'] = '暂无内容';
                            $main_arr['child'][$key]['child'][0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
                            $main_arr['child'][$key]['child'][0]['types'] = '';
                            $main_arr['child'][$key]['child'][0]['release_time'] = '';
                            $main_arr['child'][$key]['child'][0]['has_vote'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_buy'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_join'] = '0';
                            $main_arr['child'][$key]['child'][0]['has_date'] = '0';
                        }
                    }
                }

            } elseif ($node_style == 3) {     // 三级栏目
                // 获取子栏目以及条目
                $com['node_id'] = array('eq', $nodeID);
                $com['status'] = array('eq', 2);
                $com['types'] = array('exp', ' not in(100, 101) ');
                $content_arr = $content->where($com)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time', 'node_id'))->select();
                if (!empty($content_arr)) {
                    foreach ($content_arr as $content_key => $content_value) {
                        $content_arr[$content_key]['title_pic'] = $content_value['title_pic'] == '' ? SITE_URL . 'nx_heLan/public/img/no.png' : SITE_URL . 'nx_heLan/image/' . $content_value['title_pic'];
                        $content_arr[$content_key]['videos'] = $content_value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $content_value['id']))->getField('videos') : '';
                        $content_arr[$content_key]['has_vote'] = in_array($content_value['node_id'], [47, 48]) ? '1' : '0';
                        $content_arr[$content_key]['has_buy'] = in_array($content_value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                        $content_arr[$content_key]['has_join'] = in_array($content_value['node_id'], [49, 50]) ? '1' : '0';
                        $content_arr[$content_key]['has_date'] = in_array($content_value['node_id'], [79]) ? '1' : '0';
                    }
                    $main_arr = $content_arr;
                } else {
                    $main_arr[0]['id'] = '';
                    $main_arr[0]['title'] = '暂无内容';
                    $main_arr[0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
                    $main_arr[0]['types'] = '';
                    $main_arr[0]['release_time'] = '';
                    $main_arr[0]['has_vote'] = '0';
                    $main_arr[0]['has_buy'] = '0';
                    $main_arr[0]['has_join'] = '0';
                    $main_arr[0]['has_date'] = '0';
                }
            }
        }

        $output = array('data' => $main_arr, 'info' => 'Success!', 'code' => 200);
        echo json_encode($output);
    }

    /**
     * 获取每个文章的详情
     * @access public
     * @param contentID int 文章ID
     * @return mixed 成功时返回json，失败时返回错误码
     */
    public function getContents()
    {
        $contentID = I('request.contentID','','htmlspecialchars');
        $pageContent = array();

        $user = M('user');
        $node = M('node');
        $content = M('content');
        $extension = M('content_extension');

        $contents = $content->where("id = '$contentID'")->find();
        if (empty($contents)) {
            $output = array('data' => null, 'info' => 'Get data failed!', 'code' => 401);
            die(json_encode($output));
        }

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
        $pageContent['title_pic'] =  $contents['title_pic'] != '' ? SITE_URL . 'nx_heLan/image/' . $contents['title_pic'] : '';
        $pageContent['release_time'] = $contents['release_time'];
        $pageContent['title'] = $contents['title'];

        $pageContent['types'] = ($contents['node_id'] == 47 || $contents['node_id'] == 48) ? '11' : $contents['types'];

        $releaseName = $user->where("id = ".$contents['release_id'])->getField('name');
        $pageContent['releaseName'] = $releaseName;

        if ($pageContent['types'] == 11) {
            $agreeNum = $extension->where("content_id = $contentID and status = 1")->count();
            $clickArray['agreeNum'] = $agreeNum;                     // 赞成数
            $againstNum = $extension->where("content_id = $contentID and status = 2")->count();
            $clickArray['againstNum'] = $againstNum;                 // 反对数
            $pageContent['clickArray'] = $clickArray;

            $pageContent['content'] = strtr(self::make_semiAngle($contents['content']), array(' ' => '', '<br />' => ''));
        } elseif ($pageContent['types'] == 3 || $pageContent['types'] == 4) {
            $textArr = explode('^^', trim($contents['content'], '^^'));
            $picArr = explode('^^', trim($contents['pic'], '^^'));
            foreach ($picArr as $pic_key => $pic_val) {
                $picArr[$pic_key] = SITE_URL . 'nx_heLan/image/' . $pic_val;
            }
            $pageContent['content'] = $textArr;
            $pageContent['pic'] = $picArr;
        } elseif ($pageContent['types'] == 2) {
            $pageContent['content'] = $contents['content'];
            $pageContent['pic'] = SITE_URL . 'nx_heLan/image/' . $contents['pic'];
        } elseif ($pageContent['types'] == 6 || $pageContent['types'] == 7) {
            if ($contents['types'] == 6) {
                $pageContent['video'] = SITE_URL . 'movies/' . $contents['videos'];
                $pageContent['content_url'] = $contents['content_url'];
            } else {
                // 192.168.1.3++40000++admin++123456
                $stream_arr = explode('++', $contents['content']);
                $stream = $stream_arr[0];   // 监控IP
                $vsea_l = $stream_arr[1];   // 监控端口号
                $vsea_u = $stream_arr[2];   // 监控账号
                $vsea_p = $stream_arr[3];   // 监控密码
                $pageContent['content_url'] = 'http://192.168.38.21/play/?vsea_action=camera&vsea_r=rtsp://' . $stream . '&vsea_u=' . $vsea_u . '&vsea_p=' . $vsea_p . '&vsea_l=' . $vsea_l . '&vsea_h=192.168.38.22&vsea_stb=';
            }
        } else {
            $pageContent['content'] = $contents['content'];
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
     * 投票功能（含：购买、报名、投票、预约）
     * @access public
     * @param contentID int 文章的ID
     * @param status int 用户投票选择 默认:1
     * @return mixed
     */
    public function vote()
    {
        if (empty($_SESSION['phone_user'])) {
            $output = array('data' => null, 'info' => 'You are not logged in!', 'code' => 220);
            die(json_encode($output));
        }

        $contentID = I('get.contentID');        // 投票文章ID
        $status = I('get.status',1);            // 投票状态

        if(!isset($contentID) || $contentID == '') {
            $output = array('data' => null, 'info' => 'Invalid parameter!', 'code' => 407);
            die(json_encode($output));
        }

        $extension = M('content_extension');
        $content = M('content');

        $cont_node_id = $content->where("id = $contentID")->getField('node_id');

        //TODO 判断用户是否为当前社区成员；只有用户进入自己所在的社区时，才能参与投票
        if (in_array($cont_node_id, [47, 48, 49, 50])) {
            if (isset($_SESSION['phone_user']['selected_area_id'])) {
                if ($_SESSION['phone_user']['selected_area_id'] != $_SESSION['phone_user']['area_id']) {
                    $output = array('data' => null, 'info' => '非' . M('area')->where(array('id' => $_SESSION['phone_user']['selected_area_id']))->getField('name') . '人员不能参与!', 'code' => 304);
                    die(json_encode($output));
                }
            }
        }

        $where['content_id'] = $contentID;
        $where['phoner_id'] = $_SESSION['phone_user']['id'];
        $voting_record = $extension->where($where)->find();

        if (empty($voting_record)) {

            $dat['content_id'] = $contentID;
            $dat['status'] = $status;
            $dat['phoner_id'] = $_SESSION['phone_user']['id'];
            $dat['times'] = date('Y-m-d H:i:s', time());

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
            if (in_array($cont_node_id, [47, 48, 49, 50])) {
                $output = array('data' => null, 'info' => 'Please do not repeat the vote!', 'code' => 303);
            } elseif (in_array($cont_node_id, [36, 37, 38, 39, 79])) {
                $data['times'] = date('Y-m-d H:i:s', time());
                $extension->where(array('id' => $voting_record['id']))->save($data);
                $output = array('data' => null, 'info' => 'Success!', 'code' => 200);
            }
        }

        echo json_encode($output);
    }

    /**
     * 文章搜索功能（模糊查询）
     * @param title string 输入的文章名称
     * @return mixed
     */
    public function titleSearch()
    {
        $title = I('request.title', '');

        if ($title == '') {
            $output = array('data' => null, 'info' => 'Invalid parameter!', 'code' => 407);
            die(json_encode($output));
        }

        /*
        $area_selected_id = I('request.area_id', '');   //FIXME 用户所选择的区域id

        if ($area_selected_id) {
            $area_id = $_SESSION['phone_user']['selected_area_id'] = $area_selected_id;
        } else {
            if ($_SESSION['phone_user']['selected_area_id']) {
                $area_id = $_SESSION['phone_user']['selected_area_id'];
            } else {
                $area_id = $_SESSION['phone_user']['selected_area_id'] = $_SESSION['phone_user']['area_id'];
            }
        }
        $area_id = $area_id . ', 0';    // 筛选用户所在社区
        */

        $map['title'] = array('like', '%' . $title . '%');
        $map['status'] = array('eq', 2);
        $map['types'] = array('exp', ' not in(100, 101) ');
        //$map['area_id'] = array('exp', ' in(' . $area_id . ') ');

        $main_arr = M('content')->where($map)->order('orders desc')->field(array('id', 'title', 'title_pic', 'types', 'release_time','node_id'))->select();

        if (!empty($main_arr)) {
            foreach ($main_arr as $key => $value) {
                $main_arr[$key]['types'] = in_array($value['node_id'], [47, 48]) ? '11' : $value['types'];
                $main_arr[$key]['title_pic'] = $value['title_pic'] != '' ? SITE_URL . 'nx_heLan/image/' . $value['title_pic'] : SITE_URL . 'nx_heLan/public/img/no.png';
                $main_arr[$key]['videos'] = $value['types'] == 6 ? SITE_URL . 'movies/' . M('content')->where(array('id' => $value['id']))->getField('videos') : '';
                $main_arr[$key]['has_vote'] = in_array($value['node_id'], [47, 48]) ? '1' : '0';
                $main_arr[$key]['has_buy'] = in_array($value['node_id'], [36, 37, 38, 39]) ? '1' : '0';
                $main_arr[$key]['has_join'] = in_array($value['node_id'], [49, 50]) ? '1' : '0';
                $main_arr[$key]['has_date'] = in_array($value['node_id'], [79]) ? '1' : '0';
            }
        } else {
            $main_arr[0]['id'] = '';
            $main_arr[0]['title'] = '没有相匹配的数据';
            $main_arr[0]['types'] = '';
            $main_arr[0]['title_pic'] = SITE_URL . 'nx_heLan/public/img/gz.png';
            $main_arr[0]['release_time'] = '0000-00-00 00:00:00';
            $main_arr[0]['node_id'] = '';
        }

        $output = array('data' => $main_arr, 'info' => 'Success!', 'code' => 200);
        echo json_encode($output);
    }

    /**
     * 将一个字串中含有全角的数字字符、字母、空格或'%+-'等字符转换为相应半角字符
     *
     * @access public
     * @param string $str 待转换字串
     * @return string $str 处理后字串
     */
    public static function make_semiAngle($str)
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