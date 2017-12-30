<?php
require_once __DIR__ . '/Broker.php';
require_once __DIR__.'/config.php';

ini_set('display_errors','on');
error_reporting(E_ALL);


$broker = new Broker(SSO_SERVER, SSO_BROKER_ID, SSO_BROKER_SECRET);

$broker->attach(true);








/*$logout = $broker->logout();
dd($logout);*/

$username = 'zongdai001';
$password = '123qwe';
$login = $broker->login($username,$password);
dd($login);
$user = $broker->getUserInfo();
dd($user);

$url='http://dev.fl888.com/chatbet/index.php?sess='.$user['data']['sess'];
//dd($url);
$xx = $broker->apirequest($url, 'POST', '');
dd($xx);






$username = 'jackie';
$password = 'jackie123';
$login = $broker->login($username, $password);
dd($login);





$logout = $broker->logout();
dd($logout);





/*dd($user);
$islogin =  is_array($user) && $user['status_code']!=200 ? false : true;*/
$islogin = $user===null ? false : true;
$info='';
$plogout = isset($_REQUEST['logout'])?$_REQUEST['logout']:0;
$plogin = isset($_REQUEST['login'])?$_REQUEST['login']:0;


if ($plogout==1){
    if ($islogin){
        $logout = $broker->logout();
        $info = '登出->logout()';
    }
}

if ($plogin==1) {
    if ($islogin === false) {
        $username = 'jackie';
        $password = 'jackie123';
        $login = $broker->login($username, $password);
        //dd($login, $info?$info:'--');
        $info = "调用登录->login('{$username}','{$password}')";
    }
}

$user = $broker->getUserInfo();
dd($user, $info?$info:'--');





function dd($arr,$i='--'){
    $res=[];
    $res['result'] = is_array($arr) ? $arr :  $arr;
    $res['command'] ="【{$i}】";

    echo '<pre>'.json_encode($res, JSON_PRETTY_PRINT).'</pre>';
    exit;
}



