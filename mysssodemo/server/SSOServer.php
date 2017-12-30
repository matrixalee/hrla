<?php
require __DIR__.'/FileSSOCache.php';
require __DIR__.'/common.php';
/**
 * 单点登录SSO通讯SERVER端
 */
abstract class SSOServer
{
    //缓存参数
    protected $options = ['files_cache_directory' => '/tmp', 'files_cache_ttl' => 36000];

    //缓存file_put_contents
    protected $cache;

    //返回类型 jsonp url或者image
    protected $returnType;

    //商户
    protected $brokerId;

    //用户登录成功返回信息
    //protected static $user_login_info = null;

    /**
     * 构造方法.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + $this->options;
        $this->cache = $this->createCacheAdapter();
    }

    /**
     * 缓存适配器
     */
    protected function createCacheAdapter()
    {
        $adapter = new FileSSOCache($this->options['files_cache_directory']);
        $adapter->setOption('ttl', $this->options['files_cache_ttl']);

        return $adapter;
    }

    /**
     * 开启broker请求的SSO server session
     */
    public function startBrokerSession()
    {
        if (isset($this->brokerId)) return;

        $sid = $this->getBrokerSessionID();

        if ($sid === false) {
            $this->fail("broker没有发送session key", 4001);
        }

        $linkedId = $this->cache->get($sid);

        if (!$linkedId) {
            $this->fail("获取不到用户session id", 4002);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($linkedId !== session_id())
                $this->fail("session已经开始", 4003);
            return;
        }

        session_id($linkedId);
        session_start();

        $this->brokerId = $this->validateBrokerSessionId($sid);
    }

    /**
     *获取SSO server端session id
     *
     * @return bool|string
     */
    protected function getBrokerSessionID()
    {
        $headers = getallheaders();

        if (isset($headers['Authorization']) &&  strpos($headers['Authorization'], 'Bearer') === 0) {
            $headers['Authorization'] = substr($headers['Authorization'], 7);
            return $headers['Authorization'];
        }
        if (isset($_GET['access_token'])) {
            return $_GET['access_token'];
        }
        if (isset($_POST['access_token'])) {
            return $_POST['access_token'];
        }
        if (isset($_GET['sso_session'])) {
            return $_GET['sso_session'];
        }

        return false;
    }

    /**
     * 验证session id
     *
     * @param string $sid session id
     * @return string  the broker id
     */
    protected function validateBrokerSessionId($sid)
    {
        $matches = null;

        if (!preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $this->getBrokerSessionID(), $matches)) {
            $this->fail("Invalid session id", 4004);
        }

        $brokerId = $matches[1];
        $token = $matches[2];

        if ($this->generateSessionId($brokerId, $token) != $sid) {
            $this->fail("Checksum 失败: 客户端可能IP已经变动", 4005);
        }

        return $brokerId;
    }

    /**
     * 开启用户sso session
     *
     */
    protected function startUserSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    /**
     * Generate session id from session token
     *
     * @param string $brokerId
     * @param string $token
     * @return string
     */
    protected function generateSessionId($brokerId, $token)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!isset($broker)) return null;

        return "SSO-{$brokerId}-{$token}-" . hash('sha256', 'session' . $token . $broker['secret']);
    }

    /**
     * Generate session id from session token
     *
     * @param string $brokerId
     * @param string $token
     * @return string
     */
    protected function generateAttachChecksum($brokerId, $token)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!isset($broker)) return null;

        return hash('sha256', 'attach' . $token . $broker['secret']);
    }


    /**
     * Detect the type for the HTTP response.
     * Should only be done for an `attach` request.
     */
    protected function detectReturnType()
    {
        if (!empty($_GET['return_url'])) {
            $this->returnType = 'redirect';
        } elseif (!empty($_GET['callback'])) {
            $this->returnType = 'jsonp';
        } elseif (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $this->returnType = 'json';
        }
    }

    /**
     * Attach a user session to a broker session
     */
    public function attach()
    {
        $this->detectReturnType();

        if (empty($_REQUEST['broker']))
            $this->fail("参数broker不能为空", 4006);
        if (empty($_REQUEST['token']))
            $this->fail("参数token不能为空", 4007);

        if (!$this->returnType)
            $this->fail("返回url不能为空", 4008);

        $checksum = $this->generateAttachChecksum($_REQUEST['broker'], $_REQUEST['token']);

        if (empty($_REQUEST['checksum']) || $checksum != $_REQUEST['checksum']) {
            $this->fail("checksum校验失败，请检查代理broker和秘钥是否正确", 4009);
        }

        $this->startUserSession();
        $sid = $this->generateSessionId($_REQUEST['broker'], $_REQUEST['token']);

        $this->cache->set($sid, $this->getSessionData('id'));
        $this->outputAttachSuccess();
    }

    /**
     * Output on a successful attach
     */
    protected function outputAttachSuccess()
    {
        if ($this->returnType === 'json') {
            header('Content-type: application/json; charset=UTF-8');
            echo json_encode(['success' => 'attached']);
            exit;
        }

        if ($this->returnType === 'jsonp') {
            $data = json_encode(['success' => 'attached']);
            echo $_REQUEST['callback'] . "($data, 200);";
            exit;
        }

        if ($this->returnType === 'redirect') {
            $url = $_REQUEST['return_url'];
            header("Location: $url", true, 307);
            echo "重定向： <a href='{$url}'>$url</a>";
            exit;
        }
    }

    /**
     * Authenticate
     */
    public function login()
    {
        $this->startBrokerSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->fail("请用POST方式提交登录", 4010);

        $validation = $this->authenticate($_POST['username'], $_POST['password']);

        if ( $validation !== true ) {
             $this->fail($validation['error_msg'], $validation['error_code']);
        }
        $this->setSessionData('sso_user', $_POST['username']);

        $this->userInfo();
    }

    /**
     * Log out$user['xx'] =
     */
    public function logout()//[!--手机api端也要调用退出--]
    {
        $this->startBrokerSession();
        $this->setSessionData('sso_user', null);
        $this->setSessionData('sso_userinfo', null);

        output_success('退出登录');

    }

    /**
     * Ouput user information as json.
     */
    public function userInfo()
    {
        $this->startBrokerSession();
        $user = null;

        $username = $this->getSessionData('sso_user');

        if ($username) {
            $user = $this->getUserInfo($username);
        }

        $user===null ?
            $this->fail('您还未登录或者登录信息错误，请重试'.$username, 4012) :
            output_success($user);
    }


    /**
     * Set session data
     *
     * @param string $key
     * @param string $value
     */
    protected function setSessionData($key, $value)
    {
        if (!isset($value)) {
            unset($_SESSION[$key]);
            return;
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Get session data
     *
     * @param type $key
     */
    protected function getSessionData($key)
    {
        if ($key === 'id') return session_id();

        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }



    protected function fail($message, $error_code)
    {
        if ($this->returnType === 'jsonp') {
            echo $_REQUEST['callback'] . "(" . json_encode(['error' => $message]) . ", $error_code);";
            exit();
        }

        if ($this->returnType === 'redirect') {
            $url = $_REQUEST['return_url'] . '?sso_error=' . $message.",error_code【{$error_code}】";
            header("Location: $url", true, 307);
            echo "重定向： <a href='{$url}'>$url</a>";
            exit();
        }

        output_error($message, $error_code);
    }


    /**
     * 获取商户号信息
     *
     * @param string $brokerId
     * @return array
     */
    abstract protected function getBrokerInfo($brokerId);

    /**
     * 验证登录用户名和密码
     *
     * @param string $username
     * @param string $password
     * @return ValidationResult
     */
    abstract protected function authenticate($username, $password);


    /**
     * 获取用户信息
     *
     * @return array
     */
    abstract protected function getUserInfo($username);
}



if (!function_exists('getallheaders'))
{
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}