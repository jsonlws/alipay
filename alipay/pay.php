<?php



namespace app\pay\home\payapi\alipay;



use app\pay\home\payapi\alipay\lib\Common;

use app\pay\home\payapi\alipay\lib\Sign;

use app\pay\home\payapi\alipay\service\AlipayTradeService;

use app\pay\home\payapi\alipay\lib\AlipayTradeWapPayContentBuilder;

use think\facade\Cache;

class pay

{



    public function pay($order_info,$jur_info,$bar_code=''){

        $device = getDevice();

        switch ($device){

            case 'DE_SYT_H5':

                $re_pay_code = 'ali_pay_qrcode';

                break;

        }

        $method = 'alipay.trade.precreate';

        $param = [

            'out_trade_no'=>$order_info['sys_order_no'],

            'total_amount'=>$order_info['user_pay_amount'],

            'subject'=>'shop',

            'timeout_express'=>'10m'

        ];

        if(!empty($bar_code)){

            $re_pay_code = 'ali_pay_barcode';

            $method = 'alipay.trade.pay';

            $param['auth_code'] = $bar_code;

        }

        //支付宝网关https://openapi.alipaydev.com/gateway.do(沙箱使用)  https://openapi.alipay.com/gateway.do（正式）

        $aliPayGatewayUrl  = 'https://openapi.alipay.com/gateway.do';

        $notify_url = config('z9168.company_website') .'/index.php/pay/payorder/alipay_notify';//异步通知地址

        $sendData = $this->buildParams($param,$method,$notify_url,$order_info);

        $arr = Common::self()->curl($aliPayGatewayUrl,$sendData);

        $key = str_replace('.','_',$method).'_response';

        if($arr[$key]['code'] === '10000'){

            $response['msg']    = '支付宝支付请求成功';

            $response['status'] = $re_pay_code;

            if(!empty($bar_code)){

                $response["data"] = ['pay_status' => 'success', 'order_sn' => $order_info['user_pay_order_sn']];

            }else {

                $response["data"] = ['qrcode'=>'http://qr.liantu.com/api.php?text='.$arr[$key]['qr_code']];

            }

            return $response;

        }else{

            return error($arr[$key]['sub_msg']);

        }

    }





    /**

     * 异步通知回调处理

     * @param $data [支付宝传过来的参数]

     * @return mixed

     */

    public function asyncNotify($data){

        parse_str($data,$arr);

        $sign = $arr['sign'];

        unset($arr['sign']);

        unset($arr['sign_type']);

        $orderInfo = model('pay/orderex')->_where([['sys_order_no','=',$arr['out_trade_no']]])->info();

        if(empty($orderInfo)){

            exit('该订单不存在');

        }

        $info = getInPayInfo($orderInfo['shop_token'], $orderInfo['pay_mode_code'], $orderInfo['merchant_num']);

        //$private_key = $info['rsa_private_key'];

        $public_key = Sign::sign()->createPublicKey('https:'.get_home_file_path($info['certificate']));

        $paStr = Sign::sign('','')->createSign($arr);

        $checkResult = Sign::sign('',$public_key)->rsa2Check($paStr,$sign);

        if(!$checkResult){

            exit('验证签名失败');

        }

        //判断订单是否存在并且是否支付金额为商户订单中的金额

        if($orderInfo['user_pay_amount'] != $arr['total_amount']){

            exit('金额不正确');

        }

        //支付成功表标识

        $success = ['TRADE_SUCCESS','TRADE_FINISHED'];

        //订单支付成功

        $update = ['uplevel_trade_status'=>'success'];

        if(!in_array($arr['trade_status'],$success)){

            //订单失败

            $update = ['uplevel_trade_status'=>'fail'];

        }

        model('pay/orderex')->addSave($update,[['id','=',$orderInfo['id']]]);

        return 'success';

    }



    /**

     * 支付宝参数组装

     * @param $order_info

     * @param $method

     * @param $notify_url //异步通知地址

     * @param $setting //配置

     * @return mixed

     */

    private function buildParams(array $order_info,$method,$notify_url='',$setting=[]){

        $info = getInPayInfo($setting['shop_token'], $setting['pay_mode_code'], $setting['merchant_num']);

        if (!$info) {

            return error('未设置支付信息,请网站管理员设置');

        }

        // print_r($info);die;

        $publicData =  [

            //应用ID,您的APPID。

            'app_id' => $info['appid'],

            //接口名称

            'method' => $method,

            'format'=>'JSON',

            //编码格式

            'charset' => "utf-8",

            //签名方式

            'sign_type'=>"RSA2",

            //发送请求的时间，格式"yyyy-MM-dd HH:mm:ss"

            'timestamp'=>date('Y-m-d H:i:s'),

            //调用的接口版本，固定为：1.0

            'version'=>'1.0',

            //异步通知地址

            'notify_url'=>$notify_url,

            //请求参数集合

            'biz_content'=>json_encode($order_info),

            'alipay_root_cert_sn' => Sign::sign()->getRootCertSN('https:'.get_home_file_path($info['certificate_key'])),//支付宝根证书SN（alipay_root_cert_sn）

            'app_cert_sn' => Sign::sign()->getCertSN('https:'.get_home_file_path($info['rsa_public_key'])), //应用公钥证书SN（app_cert_sn）

        ];

        $private_key = $info['rsa_private_key'];

//        $public_key = $info['rsa_public_key'] ?? Sign::sign()->createPublicKey();

        $sign = Common::self()->createSign($private_key,'',$publicData);

        $publicData['sign'] = $sign;

        return $publicData;

    }



}