<?php



namespace app\pay\home\payapi\alipay;



use app\pay\home\payapi\alipay\lib\Common;

use app\pay\home\payapi\alipay\lib\Sign;

use app\pay\home\queue\Job;





class outpay

{



    /**

     * 转账

     * @param $order_info

     * @return mixed

     * $method = 'alipay.fund.trans.uni.transfer';

     *    $param = [

    'out_biz_no'=>$order_info['sys_order_no'],//商户端的唯一订单号

    'trans_amount'=>$info['user_pay_amount'],//订单总金额，单位为元，精确到小数点后两位

    'product_code'=>'TRANS_ACCOUNT_NO_PWD',  //单笔无密转账到支付宝账户固定为TRANS_ACCOUNT_NO_PWD；单笔无密转账到银行卡固定为:TRANS_BANKCARD_NO_PWD;收发现金红包固定为:STD_RED_PACKET；

    'biz_scene'=>'DIRECT_TRANSFER',//DIRECT_TRANSFER：单笔无密转账到支付宝/银行卡, B2C现金红包;  PERSONAL_COLLECTION：C2C现金红包-领红包

    'order_title'=>'转账',//转账业务的标题，用于在支付宝用户的账单里显示

    //'original_order_id'=>'',//B2C现金红包、单笔无密转账到支付宝/银行卡不需要该参数  C2C现金红包-红包领取时，传红包支付时返回的支付宝单号

    'payee_info'=>[

    'identity'=>$info['bank_card_num'],//参与方的唯一标识

    'identity_type'=>'ALIPAY_LOGON_ID',//ALIPAY_USER_ID 支付宝的会员ID  ALIPAY_LOGON_ID：支付宝登录号，支持邮箱和手机号格式

    'name'=>$info['bank_card_username'],//参与方真实姓名，如果非空，将校验收款支付宝账号姓名一致性。当identity_type=ALIPAY_LOGON_ID时，本字段必填。

    ],

    'remark'=>'转账',//$info['bank_card_num']//转账备注

    //'business_params'=>''//sub_biz_scene 子业务场景，红包业务必传，取值REDPACKET，C2C现金红包、B2C现金红包均需传入 withdraw_timeliness为转账到银行卡的预期到账时间，可选（不传入则默认为T1），取值T0

    ];

     */

    public function outpay($order_info){

        $info = json_decode($order_info['user_pay_extra_return_param'],true);

        $method = 'alipay.fund.trans.toaccount.transfer';

        $param = [

            'out_biz_no'=>$order_info['sys_order_no'],//商户转账唯一订单号

            'payee_type'=>'ALIPAY_LOGONID',  //收款方账户类型。可取值：1、ALIPAY_USERID：支付宝账号对应的支付宝唯一用户号。以2088开头的16位纯数字组成。2、ALIPAY_LOGONID：支付宝登录号，支持邮箱和手机号格式。

            'payee_account'=>$info['bank_card_num'],//收款方帐号

            'amount'=>$info['user_pay_amount'],//$info['user_pay_amount'],//转账金额，单位：元。

            'payer_show_name'=>'成都云鱼科技有限公司',//$info['bank_card_num'],//付款方姓名

            'payee_real_name'=>$info['bank_card_username'],//收款方真实姓名

            'remark'=>'转账',//$info['bank_card_num']//转账备注

        ];

        //支付宝网关https://openapi.alipaydev.com/gateway.do(沙箱使用)  https://openapi.alipay.com/gateway.do（正式）

        $aliPayGatewayUrl  = 'https://openapi.alipay.com/gateway.do';

        $sendData = $this->outPayBuildParams($param,$method,$order_info);

        $arr = Common::self()->curl($aliPayGatewayUrl,$sendData);

        $key = str_replace('.','_',$method).'_response';

        if($arr[$key]['code'] === '10000'){

            $job = new Job();

            $pushData = [

                'shop_token'=>$order_info['shop_token'],

                'pay_mode_code'=>$order_info['pay_mode_code'],

                'merchant_num'=>$order_info['merchant_num'],

                'sys_order_no'=>$arr[$key]['out_biz_no'],

                'order_id'=>$arr[$key]['order_id'],

            ];

            $job->actionWithCreateJob($pushData);

            //将支付宝的订单号保存

            model('pay/Outorderex')->addSave(['uplevel_order_no'=>$arr[$key]['order_id']],[['sys_order_no','=',$arr[$key]['out_biz_no']]]);

            $response['msg']    = '支付宝转账操作成功';

            $response['status'] = 'ali_out_pay';

            $response["data"] = ['pay_status' => 'success', 'order_sn' => $arr[$key]['out_biz_no']];

            return $response;

        }else{

            $update = ['uplevel_trade_status'=>'fail'];

            model('pay/Outorderex')->addSave($update,[['sys_order_no','=',$arr[$key]['out_biz_no']]]);

            return error($arr[$key]['sub_msg']);

        }

    }



    /**

     * 查询转账结果

     * @param $order_info

     * $order_info = [

    'out_biz_no'=>'20200508090450963927620',

    'merchant_num'=>'0',

    'pay_mode_code'=>'alipay',

    'shop_token'=>'81912274',

    'order_id'=>'20200508110070001506710053008958'

    ];测试数据

     * @return mixed

     */

    public function queryTransferAccounts($order_info){

        $method = 'alipay.fund.trans.order.query';

        $param = [

            'out_biz_no'=>$order_info['sys_order_no'],//商户转账唯一订单号

            //'order_id'=>$order_info['order_id']//支付宝转账单据号

        ];

        //支付宝网关https://openapi.alipaydev.com/gateway.do(沙箱使用)  https://openapi.alipay.com/gateway.do（正式）

        $aliPayGatewayUrl  = 'https://openapi.alipay.com/gateway.do';

        $sendData = $this->outPayBuildParams($param,$method,$order_info);

        $arr = Common::self()->curl($aliPayGatewayUrl,$sendData);

        $key = str_replace('.','_',$method).'_response';

        if($arr[$key]['code'] === '10000'){

            model('pay/Outorderex')->addSave(['uplevel_trade_status'=>$arr[$key]['status']],[['sys_order_no','=',$arr[$key]['out_biz_no']]]);

            return ['status'=>'success','msg'=>json_encode($arr[$key])];

        }else{

            return ['status'=>'fail','msg'=>json_encode($arr[$key])];

        }

    }









    /**

     * 支付宝参数组装

     * @param $order_info

     * @param $method

     * @param $setting //配置

     * @return mixed

     */

    private function outPayBuildParams(array $order_info,$method,$setting){

        $info = getInPayInfo($setting['shop_token'], $setting['pay_mode_code'], $setting['merchant_num']);

        if (!$info) {

            return error('未设置支付信息,请网站管理员设置');

        }

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

            //请求参数集合

            'biz_content'=>json_encode($order_info),

    

            'alipay_root_cert_sn' => Sign::sign()->getRootCertSN('https:'.get_home_file_path($info['certificate_key'])),//支付宝根证书SN（alipay_root_cert_sn）

            'app_cert_sn' => Sign::sign()->getCertSN('https:'.get_home_file_path($info['rsa_public_key'])), //应用公钥证书SN（app_cert_sn）



        ];

        $private_key = $info['rsa_private_key'];

        //$public_key = $info['rsa_public_key'];

        $sign = Common::self()->createSign($private_key,'',$publicData);

        $publicData['sign'] = $sign;

        return $publicData;

    }













}