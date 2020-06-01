<?php

/**

 * 支付宝支付RSA2加解密算法

 * php 版本要求7.0

 * 拓展要求 curl  open_ssl

 */

namespace app\pay\home\payapi\alipay\lib;



class Sign

{

    private $private_key;//私钥内容

    private $public_key;//公钥内容

    public function __construct($private_key='',$public_key='')

    {

        $this->private_key = $private_key;

        $this->public_key = $public_key;

    }



    public static function sign($private_key='',$public_key=''){

        return new self($private_key,$public_key);

    }



    /**

     * 利用公钥证书转成公钥

     * @param $path [支付宝公钥证书地址]

     * @return string

     */

    public function createPublicKey($path=__DIR__.'/crs/alipayCertPublicKey_RSA2.crt'){

        $pub_key = openssl_pkey_get_public(file_get_contents(trim($path)));

        $keyData = openssl_pkey_get_details($pub_key);

        $str = str_replace('-----BEGIN PUBLIC KEY-----','',$keyData['key']);

        $str = str_replace('-----END PUBLIC KEY-----','',$str);

        return $str;

    }





    /**

     * RSA2签名

     * @param $data [待签名数据]

     * @param $sign_type

     * @return string|bool

     */

    public function rsa2Sign($data,$sign_type='SHA256'){



        $search = [

            "-----BEGIN RSA PRIVATE KEY-----",

            "-----END RSA PRIVATE KEY-----",

            "\n",

            "\r",

            "\r\n"

        ];

        $private_key=str_replace($search,"",$this->private_key);

        $private_key=$search[0] . PHP_EOL . wordwrap($private_key, 64, "\n", true) . PHP_EOL . $search[1];

        $res=openssl_get_privatekey($private_key);

        if($res)

        {

            openssl_sign($data, $sign,$res,$sign_type);

            openssl_free_key($res);

        }else {

            return false;

        }

        $sign = base64_encode($sign);

        return $sign;

    }



    /**

     * RSA2验签

     * @param $data [待签名数据]

     * @param $sign [要校对的的签名结果]

     * @param $sign_type

     * @return bool

     */

    public  function rsa2Check($data, $sign,$sign_type='SHA256'){

        $search = [

            "-----BEGIN PUBLIC KEY-----",

            "-----END PUBLIC KEY-----",

            "\n",

            "\r",

            "\r\n"

        ];

        $public_key=str_replace($search,"",$this->public_key);

        $public_key=$search[0] . PHP_EOL . wordwrap($public_key, 64, "\n", true) . PHP_EOL . $search[1];

        $res=openssl_get_publickey($public_key);

        if($res)

        {

            $result = (bool)openssl_verify($data, base64_decode($sign), $res,$sign_type);

            openssl_free_key($res);

        }else{

            return false;

        }

        return $result;

    }





    /**

     * 生成签名方法

     * @param $arr

     * @return string | bool

     */

    public  function createSign($arr)

    {

        $new = array_filter($arr);

        ksort($new);

        $stringA = '';

        foreach ($new as $k => $v) {

            $stringA .= $k . '=' . $v . '&';

        }

        return substr($stringA, 0, -1);

    }



    /**

     * 从证书中提取序列号

     * @param $certPath [应用公钥证书读取地址]

     * @return string

     */

    public function getCertSN($certPath=__DIR__.'/crs/appCertPublicKey_2016102400749184.crt')

    {

        $cert = file_get_contents(trim($certPath));

        $ssl = openssl_x509_parse($cert);

        $SN = md5($this->array2string(array_reverse($ssl['issuer'])) . $ssl['serialNumber']);

        return $SN;

    }







    /**

     * 提取根证书序列号

     * @param $certPath  [支付宝根证书读取地址]

     * @return string|null

     */

    public function getRootCertSN($certPath=__DIR__.'/crs/alipayRootCert.crt')

    {

        $cert = file_get_contents(trim($certPath));

        $array = explode("-----END CERTIFICATE-----", $cert);

        $SN = null;

        for ($i = 0; $i < count($array) - 1; $i++) {

            $ssl[$i] = openssl_x509_parse($array[$i] . "-----END CERTIFICATE-----");

            if (strpos($ssl[$i]['serialNumber'], '0x') === 0) {

                $ssl[$i]['serialNumber'] = $this->hex2dec($ssl[$i]['serialNumber']);

            }

            if ($ssl[$i]['signatureTypeLN'] == "sha1WithRSAEncryption" || $ssl[$i]['signatureTypeLN'] == "sha256WithRSAEncryption") {

                if ($SN == null) {

                    $SN = md5($this->array2string(array_reverse($ssl[$i]['issuer'])) . $ssl[$i]['serialNumber']);

                } else {



                    $SN = $SN . "_" . md5($this->array2string(array_reverse($ssl[$i]['issuer'])) . $ssl[$i]['serialNumber']);

                }

            }

        }

        return $SN;

    }



    protected function array2string($array)

    {

        $string = [];

        if ($array && is_array($array)) {

            foreach ($array as $key => $value) {

                $string[] = $key . '=' . $value;

            }

        }

        return implode(',', $string);

    }



    /**

     * 0x转高精度数字

     * @param $hex

     * @return int|string

     */

    protected function hex2dec($hex)

    {

        $dec = 0;

        $len = strlen($hex);

        for ($i = 1; $i <= $len; $i++) {

            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));

        }

        return $dec;

    }





}