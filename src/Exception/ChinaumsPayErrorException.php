<?php

namespace OneHour\Chinaums\Exception;

use app\common\logic\PaymentMonitorLogic;
use app\common\model\PaymentMonitor;
use Throwable;

/**
 * Class ChinaumsPayErrorException
 * Author: zzhpeng
 * @package OneHour\Core\SDK\Exception
 */
class ChinaumsPayErrorException extends \Exception
{
    protected $message = '银联商务支付接口失败';
    protected $logData = [];
    protected $apiResult = [];

    const PAY_SUCCESS = 1000; //支付成功
    const PAY_ERROR_EXCEPTION = 4000; //通讯失败,返回的数据没有errCode
    const PAY_ERROR_ACCESS_TOKEN = 4001; //获取accessToken失败
    const PAY_ERROR= 4004; //交易失败

    //异常代码
    const ERR_CODE = [
        '0000'        => '正常',
        '1000'        => '认证失败',
        '1001'        => '授权失败',
        '9001'        => '参数校验失败',
        '9999'        => '系统错误',

        '00'          => '支付失败',
        '03'          => '无效商户',
        '13'          => '无效金额',
        '22'          => '原交易不存在',
        '25'          => '找不到原始交易',
        '30'          => '报文格式错误',
        '57'          => '不允许此交易',
        '61'          => '超出金额限制',
        '64'          => '原始金额错误',
        '92'          => '发卡方线路异常',
        '94'          => '重复交易',
        '96'          => '交换中心异常',
        '97'          => '终端号未登记',
        'A7'          => '安全处理失败',
        'ER'          => '参见具体返回信息',
        'FF'          => '查不到交易信息',
    ];

    public function __construct($message = "", $code = 0, $logData, $apiResult, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->logData = $logData;
        $this->apiResult = $apiResult;
    }

    public function getLogData(){
        return $this->logData;
    }

    public function getApiResult(){
        return $this->apiResult;
    }

    /**
     * 错误处理
     * @author: zzhpeng
     * Date: 2019/1/21
     * @param $code
     * @param $logData
     * @param $apiResult
     *
     * @return bool
     */
    public static function dealError($code, $logData, $apiResult)
    {
        switch ($code) {
            case self::PAY_ERROR_EXCEPTION :  //通讯失败,返回的数据没有errCode
                self::payErrorException($logData, $apiResult);
                break;
            case self::PAY_ERROR_ACCESS_TOKEN : //获取tokenAccess失败
                self::payErrorAccessToken($logData, $apiResult);
                break;
            case self::PAY_ERROR : //交易失败
                self::payError($logData, $apiResult);
                break;
            default :                //返回未知错误
                return false;
        }
    }

    /**
     * 返回格式不对
     * @author: zzhpeng
     * Date: 2019/1/21
     * @param $logData
     * @param $apiResult
     */
    public static function payErrorException($logData, $apiResult){
        //记录日志
        trace(
            date('Y-m-d H:i:s') . ' 返回数据有问题,无errCode异常：' . $apiResult['merchantOrderId'] .
            var_export($logData, true) . var_export($apiResult, true),
            'pay'
        );
//        支付记录 状态为待支付
        PaymentMonitorLogic::update($logData['payment_monitor_id'], $apiResult['merchantOrderId'], PaymentMonitor::payFalse(), $apiResult['errInfo'], $apiResult['errCode'], $apiResult);
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/21
     * @param $apiResult
     */
    public static function payErrorAccessToken($logData, $apiResult){
        //记录日志
        trace(
            date('Y-m-d H:i:s') . ' 获取access_token异常：' . $apiResult['merchantOrderId'] .
            var_export($logData, true) . var_export($apiResult, true),
            'pay'
        );
        //支付记录 状态为待支付
        PaymentMonitorLogic::update($logData['payment_monitor_id'], $apiResult['merchantOrderId'], PaymentMonitor::payFalse(), $apiResult['errInfo'], $apiResult['errCode'], $apiResult);
    }

    /**
     * @author: zzhpeng
     * Date: 2019/1/21
     * @param $apiResult
     */
    public static function payError($logData, $apiResult){
        //记录日志
        trace(
            date('Y-m-d H:i:s') . ' 支付失败：' . $apiResult['merchantOrderId'] .
            var_export($logData, true) . var_export($apiResult, true),
            'pay'
        );
        //支付记录 状态为待支付
        PaymentMonitorLogic::update($logData['payment_monitor_id'], $apiResult['merchantOrderId'], PaymentMonitor::payFalse(), $apiResult['errInfo'], $apiResult['errCode'], $apiResult);
    }

}