<?php
require __DIR__.'/SSOServer.php';


class MySSOServer extends SSOServer
{

    private static $brokers = [];

    private static $islogin = null;


    public function __construct(array $brokers = [], $islogin = false)//父类构造方法参数[!--mark--]
    {
        parent::__construct();
        self::$brokers = $brokers;
        self::$islogin = $islogin;
    }

    private static $users = array (
        'zongdai001' => [
            'fullname' => 'zongdai001',
            //'password' => password_hash('123qwe',PASSWORD_DEFAULT)
            'password' =>'$2y$10$dZnP/1LK9HCJB19ReZBusux7GwIijERrW5tmeJPHLq2Mq4IXExWkq',
        ],
        'demo' => [
            'fullname' => 'zongdai001',
//            'password' => password_hash('123qwe',PASSWORD_DEFAULT)
            'password' =>'$2y$10$dZnP/1LK9HCJB19ReZBusux7GwIijERrW5tmeJPHLq2Mq4IXExWkq',
        ],
    );


    protected function getBrokerInfo($brokerId)
    {
        return isset(self::$brokers[$brokerId]) ? self::$brokers[$brokerId] : null;
    }


    protected function authenticate($username, $password)
    {
        if(!isset($username) || !isset($password)){
            return array(
                'error_code' => 4020,
                'error_msg' => 'post提交参数username或者password未设置，不能为空'
            );
        }
        if (!isset(self::$users[$username]) || !password_verify($password, self::$users[$username]['password'])) {
            return array(
                'error_code' => 4021,
                'error_msg' => '用户名或者密码错误'
            );
        }

        return true;

        //if (isset($_SESSION['sso_userinfo'])){
            /*output_success($_SESSION);
            if (empty($_SESSION['userid']) || !is_numeric($_SESSION['userid'])) { // 未登陆
                $this->setSessionData('sso_user', null);
                $this->setSessionData('sso_userinfo', null);
                $sMsg = "登录失败, 请重新登录";
                return array(
                    'error_code' => 40161,
                    'error_msg' => $sMsg
                );
            }*/
            //return true;
        //}
        //这里根据项目，增加具体登录信息
    }

    protected function getUserInfo($username)
    {
        if (!isset(self::$users[$username])) return output_error("未登录或者登录信息错误，请重试", 4016);

        $user = compact('username') + self::$users[$username];
        unset($user['password']);

        return output_success($user);
    }
}
