<?php

/**
 * author         : luffy<luffy@comicool.cn>
 * creatdate    : 17-1-9 下午11:02
 * description :
 */


use Qiniu\Auth;
use Qiniu\Storage\BucketManager;

class BaseSpider
{
    public $accessKey;
    public $secretKey;
    public $bucket;
    public $domain;
    public $spider_url;
    public $token;
    public $qiniu_url;
    public $basehost;
    public $logfile;

    public function __construct()
    {
        $this->accessKey = $_ENV['QINIU_AK'];
        $this->secretKey = $_ENV['QINIU_SK'];
        $this->bucket = $_ENV['QINIU_BK'];

        $this->qiniu_url = $_ENV['CDN'];

        $this->basehost = ($_ENV["DEBUG"]) ? $_ENV['APP_URL_DEV'] : $_ENV['APP_URL'];
        $this->user = $_ENV['USER'];
        $this->pwd = $_ENV['PWD'];
        $this->user_id = $_ENV['USER_ID'];
        $this->token = $this->getToken($this->user, $this->pwd);


    }

    public function getToken($username, $password)
    {
        $url = $this->basehost . 'oauth/token';
        $post['grant_type'] = $_ENV['Grant_Type'];
        $post['client_id'] = $_ENV['Client_Id'];
        $post['client_secret'] = $_ENV['Client_Secret'];
        $post['username'] = $username;
        $post['password'] = $password;
        $post['scope'] = null;
        $data = json_decode(http_request($url, [], $post), 1);
        return $data;
    }

    /**
     * 提交数据
     * @param $api
     * @param $token
     * @param $post
     * @return mixed|string
     */
    public function postData($api, $token, $post)
    {
        $url = $this->basehost . 'api/v1/' . $api;
        $header = ['Accept' => 'application/json', 'Authorization' => $token['token_type'] . ' ' . $token['access_token']];
        $data = http_requestc($url, $header, $post);
        return $data;
    }

    /**
     * 七牛图片上传
     * @param $img_url 图片地址
     * @param null $key 指定key
     * @param string $type 图片目录
     * @return null|string
     */
    public function upImg2Qin($img_url, $key = null, $type = 'icon')
    {
        $auth = new Auth($this->accessKey, $this->secretKey);
        $bmgr = new BucketManager($auth);
        $key = ($key == '' || $key == null) ? $type . '/' . md5($img_url) . ".jpg" : $key;
        list($ret, $err) = $bmgr->fetch($img_url, $this->bucket, $key);
        if ($err !== null) {
            return $img_url;
        } else {
            return $key;
        }
    }

    /**
     * 提交网址到百度
     * @param $urls
     * @return mixed
     */
    public function postBaidu($urls)
    {
        $api = 'http://data.zz.baidu.com/urls?site=wewx.cn&token=7qJEEgUUvplX047T';
        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $api,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        return $result;
    }

    public function debug($content, $data = '')
    {
        $content = "ReportDebug:" . date('Y-m-d H:i:s') . " $content ";
        if (is_array($data)) {
            $content .= "\n" . var_export($data, true);
        } else {
            $content .= $data;
        }
        $content .= "\n";
        $filepath = __DIR__ . '/logs/' . $this->logfile;
        file_put_contents($filepath, $content, FILE_APPEND);
    }
}