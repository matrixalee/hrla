import * as param from './config'
import isJson from '../../common/util'

import express from 'express'
import cookieParser,{JSONCookies, JSONCookie} from 'cookie-parser'

class SsoParam{

    //SSO URL
    static get url(){
        return !this._url ? param.SSO_SERVER_URL : this._url;
    }liblib

    static set url(url){
        return this._url  = url;
    }

    //SSO APP ID
    static get broker(){
        return !this._broker ? param.SSO_APP_ID : this._broker;
    }

    static set broker(broker){
        return this._broker  = broker;
    }

    //SSO APP PWD
    static get secret(){
        return !this._secret ? param.SSO_APP_SECRET : this._secret;
    }

    static set secret(secret){
        return this._secret  = secret;
    }


    //cookie_lifetime
    static get cookie_lifetime(){
        return !this._cookie_lifetime ? param.SSO_COOKIE_LIFETIME : this._cookie_lifetime;
    }

    static set cookie_lifetime(cookie_lifetime){
        return this._cookie_lifetime  = cookie_lifetime;
    }
}

export default class SsoClient extends SsoParam{
    //配置参数
    static get param(){
        return {
            url:super.url,
            broker:super.broker,
            secret:super.secret,
            cookie_lifetime:super.cookie_lifetime
        }
    }


    //token
    static get token(){
        return !this._token ? null : this._token;
    }

    static set token(token){
        return this._token  = token;
    }

    static init(req, res) {
        if (SsoClient.appCookies(req, res)[SsoClient.getSSOTokenName()] ) {
            SsoClient.token = SsoClient.appCookies(req, res)[SsoClient.getSSOTokenName()];
        }/*else{
            SsoClient.generateTokenCookie(req, res)
        }*/
    }


    /**
     * 生成sso token name
     * @returns {string}
     */
    static getSSOTokenName()
    {
        return 'sso_token_'+SsoClient.param.broker.toLowerCase().replace(/[_\W]+/ig, '_');
    }

    /**
     * 生成session id
     * @returns {string}
     */
    static getSessionId()
    {
        if (SsoClient.token===null) return;

        const crypto = require('crypto');
        const hash = crypto.createHash('sha256');

        hash.update('session' + SsoClient.token + SsoClient.secret);
        const checksum = hash.digest('hex')
        return `SSO-${SsoClient.broker}-${SsoClient.token}-${checksum}`;
    }

    /**
     * 生成cookie token
     */
    static generateTokenCookie(req, res)
    {
        if (SsoClient.token!==null)
            return {
                'token':SsoClient.token
            }

        const crypto = require('crypto')
        const uniqid = require('uniqid')
        const md5 = crypto.createHash('md5')
        const suniqid = uniqid('token')
        md5.update(suniqid)
        SsoClient.token = md5.digest('hex').slice(5, 30)

        SsoClient.appCookies(req, res,{
            key:SsoClient.getSSOTokenName(),
            value:SsoClient.token,
            option:{
                path:'/',
                //expires: new Date(Date.now() + SsoClient.param.cookie_lifetime),
                //maxAge:1000*60*SsoClient.param.cookie_lifetime
            }
        })

    }

    /**
     * 清除cookies tokenclearTokenCookie
     */
    static clearTokenCookie(req, res)
    {
        SsoClient.appCookies(req, res,{
            key:SsoClient.getSSOTokenName(),
            value:null,
            option:{
                path:'/',
                //expires: new Date(Date.now() + SsoClient.param.cookie_lifetime),
                maxAge:0
            }
        })
        SsoClient.token = null
    }

    /**
     * 生成url
     * @param req
     * @param res
     * @param params
     * @returns {string}
     */
    static getAttachUrl(req, res, params = {})
    {
        SsoClient.generateTokenCookie(req, res);

        const crypto = require('crypto');
        const hash = crypto.createHash('sha256');
        hash.update('attach' + SsoClient.token + SsoClient.secret);
        const checksum = hash.digest('hex')

        var querystring=require('querystring');

        let data = {
            'command' : 'attach',
            'broker' : SsoClient.broker,
            'token' : SsoClient.token,
            'checksum' : checksumecho password_hash("rasmuslerdorf", PASSWORD_DEFAULT);
        }

        //GET
        var http = require('http');
        var url = require('url');
        var get = url.parse(req.url, true).query;

        let combineparams = Object.assign(data, get, params);

        return SsoClient.param.url + "?" + querystring.stringify(combineparams);
    }

    /**
     * 是否token有值'
     * @returns {boolean}
     */
    /*static isAttached()
    {
        return SsoClient.token !== null;
    }*/

    static attach(req, res,returnUrl = null)
    {
       if (SsoClient.token!==null)
           return {'error':'token existed already'};   //[!----]

        //var http = require('http');
        //var URL = require('url');

        /*if (returnUrl === true) {
            protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            returnUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }*/

        if (returnUrl) {
            returnUrl = req.protocol + '://' +req.hostname +":8080"+ returnUrl
        }else{
            returnUrl =req.protocol + '://' + req.hostname +":8080"+ req.originalUrl
        }
        //console.log(returnUrl)
        // let params = {return_url : 'http://localhost:8080/api/sso/test'};
        let params = {'return_url':returnUrl};
        let url = SsoClient.getAttachUrl(req, res,params);

        //console.log('attach:', url)

        res.redirect(307, url)
        //process.exit(0)
    }

    /**
     * 得到command请求的url
     * @param command
     * @param params
     * @returns {*}
     */
    static getRequestUrl(command, params = {})
    {
        params['command'] = command;
        let querystring=require('querystring');
        return SsoClient.param.url + '?' + querystring.stringify(params);
    }

    /**
     * 请求sso server
     * @param $method
     * @param $command
     * @param $data
     * @returns {*}
     */
    static request(method, command, data = null)
    {
        if (SsoClient.token===null) {
            return {error:'No token, request need token'}
        }

        let _method = !method ? 'POST' : method
        let _data = (data === null) ? {} : data
        let ssoserverurl = SsoClient.getRequestUrl(command, _data);

        //testing
        // ssoserverurl = 'https://slack.com/api/api.test'
        //_method = 'POST'
        //console.log('ssoserverurl',ssoserverurl+_method)

        var request = require('request')

        var options = {
            uri:ssoserverurl,
            method: _method,
            //body: _method==='POST' ? JSON.stringify(_data) : '',
            // body:'{username:"demo1",password:"demo123",command:"login"}',
            headers: {
                //"content-type": "application/json",
                'charset': 'UTF-8',
                'Authorization': 'Bearer '+SsoClient.getSessionId()
            },
            //encoding:'utf8'
            /*multipart: [
                {
                    'content-type': 'application/json',
                    body: _method==='POST' ? JSON.stringify(_data) : '',
                }
            ],*/
        }
        if(_method==='POST'){
            options.form=_data
        }

        //console.log('options',options)

        var req_ssoserver = function(options) {
            return new Promise(function(resolve, reject) {
                request(options, function(err, response, body) { //[!--重写--]
                    if(ssoserverurl)
                        console.log('返回结果ssoserverurl：',ssoserverurl);
                    if(err)
                        console.log('返回结果err：',err);
                    if(response)
                        console.log('返回结果response：',response.statusCode);
                    if(body)
                        console.log('返回结果body：',body);


                    if (typeof response === 'object' && response.hasOwnProperty('statusCode') && response.statusCode == 403) {
                        //SsoClient.clearTokenCookie(req.res);
                        let results=JSON.parse(body);
                        console.log('results：',results);
                        resolve(results);
                    }else if (err) {
                        console.log('err：',err);
                        resolve(err);
                    } else {
                        try{
                            let results=JSON.parse(body);
                            console.log('results body：',results);
                            resolve(results);
                        }catch (e){
                            resolve(body+'!'+e.name + ": " + e.message);
                        }

                        // resolve(body);
                    }
                    /*if(!err && response.statusCode == 200){
                     if(body!=='null'){
                     results=JSON.parse(body);
                     resolve(results);
                     }
                     }*/
                });
            })
        };

        var res_ssoserver =async function(options) {
            try {
                let result = await req_ssoserver(options);
                return result;
            } catch (err) {
                return {
                    name: 'WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW'
                }
            }
        }

        return res_ssoserver(options)
    }

    /**
     * 登录sso
     * @param username
     * @param password
     * @returns {*}
     */
    static login(username = null, password = null)
    {
       return SsoClient.request('POST', 'login', {username,password});
    }


    /**
     * 注销sso
     */
    static logout()
    {
        SsoClient.request('POST', 'logout', {});
    }

    static get isLogin()
    {
        return SsoClient.request('GET', 'userInfo',{})
    }


    static test(req, res)
    {
        SsoClient.init(req, res)
        //SsoClient.clearTokenCookie(req, res)
        //SsoClient.generateTokenCookie(req, res)
        //SsoClient.attach(req, res,'/api/sso/test?login=2')

        console.log('SsoClient.token====>',SsoClient.token)
        //if(SsoClient.token===null ){
        SsoClient.attach(req, res)
        //}

        //判断是否登录。。。。。。。。。。。。。。。


        //console.log('req',req.query)
        // console.log('res',Object.keys(res))
        if(req.query.login==1){
            //return {a:1}
            return SsoClient.login('demo1','demo123')
        }

        if(req.query.login==2){
            return SsoClient.request('POST', 'userInfo',{})
        }

        if(req.query.cmd){
            //return {a:1}
            return SsoClient.request('POST', req.query.cmd);
        }





        /*return {
            a:SsoClient.request('GET', 'userInfo',{}),
            //b:SsoClient.login('demo1','demo123')
        }*/

        return SsoClient.request('GET', 'userInfo',{})

        return SsoClient.login('demo1','demo123')

        // return SsoClient.isLogin;









        //return SsoClient.token
        //return SsoClient.login('demo1','demo123')

        //SsoParam.url = 'https://slack.com/api/api.test'
        //return SsoClient.request('POST', 'test')

        return {
            SSOTokenName:SsoClient.getSSOTokenName(),
            token:SsoClient.token,
            cookies:SsoClient.appCookies(req, res),
            sessionid:SsoClient.getSessionId()
        }


    }

    /**
     * 获取所有和生成单个cookies
     * @param req
     * @param res
     * @param setcookies
     * @returns {*|Object}
     */
    static appCookies(req, res, setcookies=null)
    {
        let r_cookie = require('cookie')

        const app = express()
        app.use(cookieParser());

        //res.cookie('test', 'newcookie', { domain: '.example.com', path: '/admin',expires: new Date(Date.now() + 900000) });

        //删除cookie
        //res.clearCookie(name [, options])


        /*let x= {
         key:SsoClient.getSSOTokenName(),
         value:'sdjfksjdf798s7f9s7adfsdfksdaj',
         option:{}
         }
         setcookies = x;*/
        if( isJson(setcookies)){
            //[!--后续补充--]
            res.cookie(setcookies.key, setcookies.value, setcookies.option)
        }
        // res.cookie(SsoClient.getSSOTokenCookieName(), 'sdjfksjdf798s7f9s7adfsdfksdaj')
        // res.cookie('test', {a:1})

        let v_cookies = req.headers.cookie || null;    //保存对象地址，提高运行效率

        // req.cookies = Object.create(null);    //创建一个对象，解析后的且未使用签名的cookie保存在req.cookies中
        req.cookies = r_cookie.parse(v_cookies);    //与express中调用cookie.serialize()对应，解析cookie
        //console.log('parse',req.cookies);

        req.cookies = JSONCookies(req.cookies);    // JSON字符序列转化为JSON对象
        //console.log('JSONCookies',req.cookies);

        return req.cookies

    }

}

//
// console.log(SsoParam.url);
// SsoClient.broker = 'Q__^jjjkIIooo__'
// console.log(SsoClient.getCookieName());