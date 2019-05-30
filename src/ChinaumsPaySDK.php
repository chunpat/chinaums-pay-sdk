<?php

namespace OneHour\Chinaums;

use OneHour\Chinaums\Exception\ChinaumsPayErrorException;
use think\facade\Cache;

/**
 * @zzhpeng
 * Class ChinaumsPaySDK
 * @package OneHour\Chinaums
 */
class ChinaumsPaySDK
{
    /**
     * 测试
    const APPID = 'f0ec96ad2c3848b5b810e7aadf369e2f';
    const APPKEY = '775481e2556e4564985f5439a5e6a277';
    const ACCESS_TOKEN_API_URL = 'http://58.247.0.18:29015/v1/token/access';
    const PAY_API_URL = 'http://58.247.0.18:29015/v2/poslink/transaction/pay';
     *
     * 正式
    const APPID = '';
    const APPKEY = '';
    const ACCESS_TOKEN_API_URL = 'https://api-mop.chinaums.com/v1/token/access';
    const PAY_API_URL = 'https://api-mop.chinaums.com/v2/poslink/transaction/pay';
     *
     *
     */
    const CACHE_CHINAUMS_ACCESS_TOKEN = 'cache_chinaums_access_token';


    /**
     * 获取时间戳
     * @author: zzhpeng
     * Date: 2019/1/18
     * @return false|string
     */
    public static function getTimestamp(){
        return date('YmdHis');
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/25
     * @return mixed
     */
    public static function getAppId(){
        return env('CHINAUMS_PAY.APPID');
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/25
     * @return mixed
     */
    public static function getAppKey(){
        return env('CHINAUMS_PAY.APPKEY');
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/25
     * @return mixed
     */
    public static function getAccessTokenApiUrl(){
        return env('CHINAUMS_PAY.ACCESS_TOKEN_API_URL');
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/25
     * @return mixed
     */
    public static function getPayApiUrl(){
        return env('CHINAUMS_PAY.PAY_API_URL');
    }
    
    /**
     * 获取accessToken
     * @author: zzhpeng
     * Date: 2019/1/21
     * @return mixed
     * @throws ChinaumsPayErrorException
     */
    public static function getAccessToken(){
        //todo 要写缓存 accessToken
        if(!Cache::get(self::CACHE_CHINAUMS_ACCESS_TOKEN)){
            $apiUrl = self::getAccessTokenApiUrl();
            $appId = self::getAppId();  //appId
            $appKey = self::getAppKey();  //$appKey
            $timestamp = self::getTimestamp();  //yyyyMMddHHmmss
            $nonce = self::getRandCode(64);  //长度不超过128位
            $signature = self::getSignature($appId,$timestamp,$nonce,$appKey);  //SHA1(appId+timestamp+nonce+appKey)
            $param = [
                'appId'=>$appId,
                'timestamp'=>$timestamp,
                'nonce'=>$nonce,
                'signature'=>$signature,
            ];
            $accessTokenJson = httpPost($apiUrl,json_encode($param), ["Content-type: application/json;charset=UTF-8"],null,function($aStatus, $sContent){
                return $sContent;
            });

            //json返回转换成array
            $accessTokenArray = json_decode($accessTokenJson,true);
            //判断是否有http_code
            if(isset($accessTokenArray['errCode']) && $accessTokenArray['errCode'] == '0000') {
                //数据没问题做cache
                Cache::set(self::CACHE_CHINAUMS_ACCESS_TOKEN,$accessTokenJson,3000); //银联那边有效期是一小时，这里提前600秒失效
                return $accessTokenArray['accessToken'];
            }else{
                throw new ChinaumsPayErrorException('获取accessToken失败',4001,$param,$accessTokenArray);
            }
        }else{
            //取出来是json，转换
            return json_decode(Cache::get(self::CACHE_CHINAUMS_ACCESS_TOKEN),true)['accessToken'];
        }
    }

    /**
     * 获取随机码
     * @author: zzhpeng
     * Date: 2019/1/18
     * @param int    $len
     * @param string $format
     *
     * @return string
     */
    public static function getRandCode($len=8,$format='ALL'){
        $is_abc = $is_numer = 0;
        $password = $tmp ='';
        switch($format){
            case 'ALL':
                $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                break;
            case 'CHAR':
                $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                break;
            case 'NUMBER':
                $chars='0123456789';
                break;
            default :
                $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                break;
        }
        mt_srand((double)microtime()*1000000*getmypid());
        while(strlen($password)<$len){
            $tmp =substr($chars,(mt_rand()%strlen($chars)),1);
            if(($is_numer <> 1 && is_numeric($tmp) && $tmp > 0 )|| $format == 'CHAR'){
                $is_numer = 1;
            }
            if(($is_abc <> 1 && preg_match('/[a-zA-Z]/',$tmp)) || $format == 'NUMBER'){
                $is_abc = 1;
            }
            $password.= $tmp;
        }
        if($is_numer <> 1 || $is_abc <> 1 || empty($password) ){
            $password = self::getRandCode($len,$format);
        }
        return $password;
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/18
     * @param string $appId
     * @param string $timestamp
     * @param string $nonce
     * @param string $appKey
     *
     * @return string
     */
    public static function getSignature(string $appId,string $timestamp,string $nonce,string $appKey){
        return sha1($appId . $timestamp . $nonce . $appKey);
    }

    /**
     * 扫码枪收款
     * @author: zzhpeng
     * Date: 2019/1/25
     * @param string $tradeNo 流水号
     * @param array  $chinaums
     * @param string $scanCode
     * @param float  $amount
     * @param string $storeDesc
     * @param string $operatorDesc
     * @param string $merchantRemark
     *
     * @return array
     * @throws ChinaumsPayErrorException
     */
    public static function microPay(string $tradeNo,array $chinaums, string $scanCode ,float $amount,string $storeDesc,string $operatorDesc,string $merchantRemark = '扫码支付'){
        $param = [
            'merchantCode'=> $chinaums['merc_id'],  //商户号
            'terminalCode'=> $chinaums['term_id'],  //终端号
            'transactionAmount'=> (INT)($amount * 100),  //交易金额 单位：分
            'transactionCurrencyCode'=> '156',  //交易币种 需填入156 RMB
            'merchantOrderId' => $tradeNo,  //商户订单号 流水号
            'merchantRemark' => $merchantRemark,  //商户备注
            'payMode'=> 'CODE_SCAN',  //支付方式 E_CASH – 电子现金 SOUNDWAVE – 声波 NFC – NFC CODE_SCAN – 扫码 MANUAL – 手输
            'payCode'=> $scanCode, //支付码
            'goods'=>  [],  //商品信息 不做记录
            'srcReserved'=>  '', //商户冗余信息
            'storeId'=> $storeDesc, //门店编号
            'limitCreditCard'=> false, //是否限制信用卡 布尔型
            'operatorId'=>  $operatorDesc,  //操作员编号
            //'bizIdentifier' => ''  //业务标识  标识接入的具体业务,除非特殊说明，一般不需要上送
        ];
        $accessToken = self::getAccessToken();
        $header[] = "Authorization:OPEN-ACCESS-TOKEN AccessToken=". $accessToken;
        $header[] = "Content-type: application/json;charset=UTF-8";

        $microPayParam = json_encode($param);
        $microPayJson = httpPost(self::getPayApiUrl(),$microPayParam, $header,null,function($aStatus, $sContent){
            return $sContent;
        });

        return array_merge(json_decode($microPayJson,true),['merchantOrderId'=>$tradeNo]);
    }
}