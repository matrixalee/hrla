<?php

require __DIR__.'/common.php';

/**
 * SSO 代理.
 *
 */
class Broker
{

    //sso server地址
    protected $url;

    //代理
    public $broker;

    //代理秘钥
    protected $secret;

    //token
    public $token;

    //用户信息
    protected $userinfo;

    //cookies过期值
    protected $cookie_lifetime;

    /**
     * 构造方法
     * @param $url
     * @param $broker
     * @param $secret
     * @param int $cookie_lifetime
     */
    public function __construct($url, $broker, $secret, $cookie_lifetime = 3600)
    {
        if (!$url)
            output_error('SSO server URL not specified', 4500);
        if (!$broker)
            output_error('SSO broker id not specified', 4501);
        if (!$secret)
            output_error('SSO broker secret not specified', 4502);

        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;
        $this->cookie_lifetime = $cookie_lifetime;

        if (isset($_COOKIE[$this->getCookieName()]))
            $this->token = $_COOKIE[$this->getCookieName()];
    }

    //sso cookie名
    protected function getCookieName()
    {
        return 'sso_token_' . preg_replace('/[_\W]+/', '_', strtolower($this->broker));
    }

    /**
     * 生成sesssion id
     *
     * @return string
     */
    protected function getSessionId()
    {
        if (!isset($this->token)) return null;

        $checksum = hash('sha256', 'session' . $this->token . $this->secret);
        return "SSO-{$this->broker}-{$this->token}-$checksum";
    }

    /**
     * 生成cokkies token
     */
    public function generateToken()
    {
        if (isset($this->token)) return;

        $this->token = base_convert(md5(uniqid(rand(), true)), 16, 36);
        setcookie($this->getCookieName(), $this->token, time() + $this->cookie_lifetime, '/');
    }

    /**
     * 清理token
     */
    public function clearToken()
    {
        setcookie($this->getCookieName(), null, 1, '/');
        setcookie('_sessionHandler	', null, null, '/');
        $this->token = null;
    }

    /**
     * 检查是否token存在
     *
     * @return boolean
     */
    public function isAttached()
    {
        return isset($this->token);
    }

    /**
     * 生成请求 SSOserver url，
     *
     * @param array $params
     * @return string
     */
    public function getAttachUrl($params = [])
    {
        $this->generateToken();

        $data = [
            'command' => 'attach',
            'broker' => $this->broker,
            'token' => $this->token,
            'checksum' => hash('sha256', 'attach' . $this->token . $this->secret)
        ] + $_GET;

        $purl = parse_url($this->url);
        $surl = $purl['scheme'].'://'.$purl['host'];
        $surl .= isset($purl['port'])?':'.$purl['port']:'';
        $surl .= isset($purl['path'])?$purl['path']:'';
        $surl .= isset($purl['query'])?'?'.$purl['query'].'&'.http_build_query($data + $params): "?" . http_build_query($data + $params );

        return $surl;


        //return $this->url . "?" . http_build_query($data + $params);
    }

    /**
     * 生成请求 SSOserver url，重定向返回
     *
     * @param string|true $returnUrl  The URL the client should be returned to after attaching
     */
    public function attach($returnUrl = null)
    {
        if ($this->isAttached()) return;

        if ($returnUrl === true) {
            $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $returnUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        $params = ['return_url' => $returnUrl];
        $url = $this->getAttachUrl($params);

        header("Location: $url", true, 307);
        echo "重定向: <a href='$url'>$url</a>";
        exit();
    }

    /**
     * 获取请求sso server的url
     * @param $command
     * @param array $params
     * @return string
     */
    protected function getRequestUrl($command, $params = [])
    {
        $params['command'] = $command;

        $purl = parse_url($this->url);
        $surl = $purl['scheme'].'://'.$purl['host'];
        $surl .= isset($purl['port'])?':'.$purl['port']:'';
        $surl .= isset($purl['path'])?$purl['path']:'';
        $surl .= isset($purl['query'])?'?'.$purl['query'].'&'.http_build_query( $params): "?" . http_build_query( $params );
        return $surl;


        //return $this->url . '?' . http_build_query($params);
    }

    /**
     * 请求 SSO server.
     *
     * @param $method
     * @param $command
     * @param null $data
     * @return mixed|null
     */
    protected function request($method, $command, $data = null)
    {
        if (!$this->isAttached()) {
            output_error('No token', 4503);
        }
        $url = $this->getRequestUrl($command, !$data || $method === 'POST' ? [] : $data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Authorization: Bearer '. $this->getSessionID()]);

        if ($method === 'POST' && !empty($data)) {
            $post = is_string($data) ? $data : http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch) != 0) {
            $message = 'Server request failed: ' . curl_error($ch);
            output_error($message, 4504);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        list($contentType) = explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE));

        if ($contentType != 'application/json') {
            $message = 'Expected application/json response, got ' . $contentType;
            $sso_error = isset($_REQUEST['sso_error']) ? $_REQUEST['sso_error'] : '';
            output_error($sso_error?$sso_error:$message, 4505);
        }


        $data = json_decode($response, true);
        $data['command'] = $command;//临时调试信息
        $data['url'] = $url;//临时调试信息
        if ($httpCode == 4005) {
            $this->clearToken();
            output_error($data['error'] ?: $response, $httpCode, 4506);
        }
        if ($httpCode >= 4000)
            output_error($data['error'] ?: $response, $httpCode, 4507);
        
        return $data;
    }


    /**
     * 登录
     *
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login($username = null, $password = null)
    {
        if (!isset($username) && isset($_POST['username'])) $username = $_POST['username'];
        if (!isset($password) && isset($_POST['password'])) $password = $_POST['password'];

        $result = $this->request('POST', 'login', compact('username', 'password'));

        /*$s = isset($result['data']['sessionid'])? $result['data']['sessionid']:null;
        $c = isset($_COOKIE["_sessionHandler"])? $_COOKIE["_sessionHandler"]:null;
        if ($s && $c!=$s){
            setcookie("_sessionHandler", $s, time()+3600,'/');
            $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $url",302);
        }*/

        $this->userinfo = $result;

        return $this->userinfo;
    }

    /**
     * 登出
     */
    public function logout()
    {
        return $this->request('POST', 'logout', 'logout');
    }

    /**
     * 获取用户信息
     *
     * @return object|null
     */
    public function getUserInfo()
    {
        if (!isset($this->userinfo)) {
            $this->userinfo = $this->request('GET', 'userInfo');
            /*$s = isset($this->userinfo['data']['sessionid'])? $this->userinfo['data']['sessionid']:null;
            $c = isset($_COOKIE["_sessionHandler"])? $_COOKIE["_sessionHandler"]:null;
            if ($s && $c!=$s){
                setcookie("_sessionHandler", $s, time()+3600,'/');
                $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
                $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: $url",302);
            }*/
        }
        return $this->userinfo;
    }

    /**
     * 魔术方法处理request
     *
     * @param string $fn
     * @param array  $args
     * @return mixed
     */
    public function __call($fn, $args)
    {
        $sentence = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $fn));
        $parts = explode(' ', $sentence);

        $method = count($parts) > 1 && in_array(strtoupper($parts[0]), ['GET', 'DELETE'])
            ? strtoupper(array_shift($parts))
            : 'POST';
        $command = join('-', $parts);

        return $this->request($method, $command, $args);
    }


    public function apirequest($url,$method,  $data)
   {

       $ch = curl_init($url);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
       curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

       if ($method === 'POST' && !empty($data)) {
           $post = is_string($data) ? $data : http_build_query($data);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
       }

       $response = curl_exec($ch);
       if (curl_errno($ch) != 0) {
           $message = 'Server request failed: ' . curl_error($ch);
           output_error($message, 4504);
       }

       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
       list($contentType) = explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE));

       if ($contentType != 'application/json') {
           $message = 'Expected application/json response, got ' . $contentType;
           $sso_error = isset($_REQUEST['sso_error']) ? $_REQUEST['sso_error'] : '';
           output_error($sso_error?$sso_error:$message, 4505);
       }


       $data = json_decode($response, true);
       //$data['command'] = $command;//临时调试信息
       if ($httpCode == 4005) {
           $this->clearToken();
           output_error($data['error'] ?: $response, $httpCode, 4506);
       }
       if ($httpCode >= 4000)
           output_error($data['error'] ?: $response, $httpCode, 4507);

       return $data;
   }
}
