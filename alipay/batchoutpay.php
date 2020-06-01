<?php
/**
 * 批量付款(暂时不使用)
 */

namespace app\pay\home\payapi\alipay;

use app\pay\home\payapi\alipay\lib\Sign;

class batchoutpay
{
    /**
     * 支付宝批量付款业务执行
     */
    public function bulkPayment(){
        $sendData = $this->publicParams();
        $newUrl = 'https://mapi.alipay.com/gateway.do?'.Sign::sign()->createSign($sendData);
        $result = $this->https_get($newUrl);
        print_r($result);
    }



    private function publicParams(){
        $detail = $this->createPayData();
        $params = [
            //基本参数
            'service'=>'batch_trans_notify',//批量付款接口名称
            'partner'=>'2088631839608712',//合作身份者ID
            '_input_charset'=>'UTF-8',//商户网站使用的编码格式，如UTF-8、GBK、GB2312等
            'notify_url'=>config('z9168.company_website') .'/index.php/pay/outpayorder/alioutpay_notify',//服务器异步通知页面路径
            //业务参数
            'account_name'=>'成都云鱼科技有限公司',//付款方的支付宝账户名
            'detail_data'=>$detail['detail_data'],//付款的详细数据
            'batch_no'=>date('YmdHis'),//批量付款批次号
            'batch_num'=>$detail['batch_num'],//付款总笔数
            'batch_fee'=>$detail['batch_fee'],//付款文件中的总金额。格式：10.01，精确到分。
            'email'=>'mderzhanggui@163.com',//付款方的支付宝账号，支持邮箱和手机号2种格式
            'pay_date'=>date('Ymd'),//支付时间（必须为当前日期）。格式：YYYYMMDD。
        ];
        $params['sign_type']='MD5';//DSA、RSA、MD5三个值可选，必须大写'
        $params['sign'] = md5(Sign::sign()->createSign($params).'3akhqlouhi2px4cpvo5vpxje27bawbuw');
        return $params;
    }

    /**
     * 组装付款的详细数据
     * 格式为：流水号1^收款方账号1^收款账号姓名1^付款金额1^备注说明1|流水号2^收款方账号2^收款账号姓名2^付款金额2^备注说明2。
     * 每条记录以“|”间隔。
     * 流水号不能超过64字节，收款方账号小于100字节，备注不能超过200字节。当付款方为企业账户，且转账金额达到（大于等于）50000元，备注不能为空。
     * @return array
     */
    private function createPayData(){
        $arr = [
            [
                'serial_number'=>date('YmdHis').rand(10000,99999),//流水号
                'collection_account_no'=>'zxh_830@163.com',//收款账号
                'username'=>'张晓红',//收款账号姓名
                'money'=>'0.01',//付款金额
                'remarks'=>'付款'//备注说明
            ]
        ];
        $str = '';
        $totalMoney = 0;
        foreach ($arr as $val){
            $str .= $val['serial_number'].'^'.$val['collection_account_no'].'^'.$val['username'].'^'.$val['money'].'^'.$val['remarks'].'|';
            $totalMoney += $val['money'];
        }
        return ['detail_data'=>substr($str, 0, -1),'batch_num'=>sizeof($arr),'batch_fee'=>$totalMoney];
    }

    /**
     * 异步通知
     */
    public function asyncNotify(){


    }


    /*
   * 发起GET网络提交
   * @params string $url : 网络地址
   */
    private function https_get($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE) ;
        curl_setopt($curl, CURLOPT_TIMEOUT,60);
        if (curl_errno($curl)) {
            return 'Errno'.curl_error($curl);
        }
        else{$result=curl_exec($curl);}
        curl_close($curl);
        return $result;
    }

}