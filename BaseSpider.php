<?php

/**
 * author         : luffy<luffy@comicool.cn>
 * creatdate    : 17-1-9 下午11:02
 * description :
 */

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
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
    public $host;
    public $ref;

    public function __construct()
    {
        $this->accessKey = $_ENV['QINIU_AK'];
        $this->secretKey = $_ENV['QINIU_SK'];
        $this->bucket = $_ENV['QINIU_BK'];
        $this->filePath = 'Upload/images/';
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
        $data = http_request($url, $header, $post);
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
        $token = $auth->uploadToken($this->bucket);
        $filename = $this->down_img($img_url, $this->filePath . $type . '/', $this->host, $this->ref);
       echo $filePath = $this->filePath . $type . '/' . $filename;
        if ($filename) {
            $key = ($key == '' || $key == null) ? $type . '/' . $filename : $key;
            $uploadMgr = new UploadManager();
            list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
            if ($err !== null) {
                return $key;
            } else {
                return $ret['key'];
            }
        }

    }

    /**
     * 七牛图片上传
     * @param $img_url 图片地址
     * @param null $key 指定key
     * @param string $type 图片目录
     * @return null|string
     */
    public function upImg2QinFt($img_url, $key = null, $type = 'icon')
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


    public function down_img($url, $filepath, $host, $ref)
    {
        $ip = get_ip();
        $header = [
            'Host' => $host,
            'Referer' => $ref,
            'CLIENT-IP' => $ip,
            'X-FORWARDED-FOR' => $ip,
        ];
        foreach ($header as $n => $v) {
            $headerArr[] = $n . ':' . $v;
        }
        print_r($url);
        print_r($header);
        $USER_AGENT = isset($_SERVER ['HTTP_USER_AGENT']) ? $_SERVER ['HTTP_USER_AGENT'] : 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       echo $content = curl_exec($ch);
        $curlinfo = curl_getinfo($ch);
        curl_close($ch);
        if ($curlinfo['http_code'] == 200) {
            if ($curlinfo['content_type'] == 'image/jpeg') {
                $exf = '.jpg';
            } else if ($curlinfo['content_type'] == 'image/png') {
                $exf = '.png';
            } else if ($curlinfo['content_type'] == 'image/gif') {
                $exf = '.gif';
            }
            $filename = md5($url) . $exf;
            if (!file_exists($filepath . $filename))
                $res = file_put_contents($filepath . $filename, $content);
            return $filename;
        } else {
            return false;
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