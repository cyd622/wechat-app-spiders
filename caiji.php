<?php
require_once 'function.php';
require_once __DIR__ . '/vendor/autoload.php';
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
date_default_timezone_set('PRC');
error_reporting(0);
set_time_limit(0);

class Caiji
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $app_url;
    private $art_url;
    private $token;

    public function __construct()
    {
        $this->accessKey = 'O0lkrGDG8Nel6rr1FbaRqR-efJtKiD6CNwNCUTis';
        $this->secretKey = 'NJSBbCXgaKfkBAaKx406GRiRfHiK2wMrXx4xaALD';
        $this->bucket = 'xiaochengxu';

        $this->domain = 'https://minapp.com';
        $this->app_url = $this->domain . '/api/v3/trochili/miniapp/?&limit=20&offset=0';
        $this->art_url = $this->domain . '/api/v3/trochili/post/?post_type=article&limit=20&offset=0';
        $this->qiniu_url = 'http://cdn.wewx.cn/';

        $this->basehost = 'http://wewx.app/';
        $this->user = '3073721569@qq.com';
        $this->pwd = 'juan@810';
        $this->user_id = 2;
        $this->token = $this->getToken($this->user, $this->pwd);
    }

    public function run()
    {
        /*$url = 'http://media.ifanrusercontent.com/media/user_files/trochili/ad/07/ad07087fa027a86abf9402a3534259e6abadb792-3de1c6ad0258927562f01bdef5b470dc475cbbbb.jpg';
        $sat = $this->upImg2Qin($url);
        print_r($sat);*/

        $this->getApp($this->user_id);

    }

    private function getToken($username, $password)
    {
        $url = $this->basehost . 'oauth/token';
        $post['grant_type'] = 'password';
        $post['client_id'] = '1';
        $post['client_secret'] = 'ZogtccW1oB6rQfM3UsXm12vigC2qtdg4wKEMApPf';
        $post['username'] = $username;
        $post['password'] = $password;
        $post['scope'] = null;
        $data = json_decode(http_request($url, [], $post), 1);
        return $data;
    }

    private function postData($api, $token, $post)
    {
        $url = $this->basehost . 'api/v1/' . $api;
        $header = ['Accept' => 'application/json', 'Authorization' => $token['token_type'] . ' ' . $token['access_token']];
        $data = http_request($url, $header, $post);
        return $data;
    }

    /**
     * 采集知晓程序app列表
     */
    private function getApp($user_id)
    {
        $i = 0;
        do {
            $app_list = http_request($this->app_url);
            $data_list = json_decode($app_list, 1);
            $is_next = $data_list['meta']['next'];

            foreach (array_reverse($data_list['objects']) as $key => $value) {

                echo '应用=>' . $value['name'] . '  正在入库...' . PHP_EOL;
                $wxapps['user_id'] = $user_id;
                $wxapps['title'] = $value['name'];
                $wxapps['description'] = $value['description'];
                $wxapps['source'] = 'minapp';
                $wxapps['source_id'] = $value['id'];
                $wxapps['icon'] = $this->upImg2Qin($value['icon']['image']);
                $wxapps['qrcode'] = $this->upImg2Qin($value['qrcode']['image'], null, 'qrcode');
                $wxapps['tags'] = trim(array_reduce($value['tag'], function ($ids, $res) {
                    return $ids . $res['name'] . ',';
                }), ",");

                $wxapps['screens']=trim(array_reduce($value['screenshot'], function ($ids, $res) {
                    return $ids . $this->upImg2Qin($res['image'], null, 'screenshot') . ',';
                }), ",");

                #var_dump($wxapps);
                $status = $this->postData('wxapp', $this->token, $wxapps);
                $jsonp = json_decode($status, 1);
                var_dump($status);
                if (isset($jsonp['status']) && $jsonp['status'] == 'success')
                    $i++;
                else
                    echo '应用=>' . $value['name'].'#'.$value['id'] . '  正在入库失败...' . PHP_EOL;
            }
            if ($is_next != null)
                $this->app_url = $this->domain . $is_next;
            else
                break;

        } while (1);
        echo '所有应用已采集入库，本次入库 【' . $i . '】  条记录...' . PHP_EOL;

    }

    /**
     * 采集知晓程序的文章
     */
    private function getArticle()
    {
        $i = 0;
        do {
            $art_list = http_request($this->art_url);
            $data_list = json_decode($art_list);
            $is_next = $data_list->meta->next;
            $iterms = $data_list->objects;
            foreach ($iterms as $key => $iterm) {
                $is_insert = $this->db->get("posts", 'id', ["post_title[~]" => $iterm->title]);
                if ($is_insert) {
                    echo '文章=>' . $iterm->title . '  已存在(id=' . $is_insert . ')...' . PHP_EOL;
                } else {
                    echo '文章=>' . $iterm->title . '  正在入库...' . PHP_EOL;
                    $post['post_title'] = $iterm->title;
                    $post['cover_image'] = $this->upImg2Qin($iterm->cover_image->image, null, 'post_cover');
                    $post['auth_id'] = 0;

                    $data = $this->contProces($iterm->content);
                    $post['content'] = $data['content'];
                    $post['post_type'] = 'article';
                    $post['tags'] = 0;
                    $post['created_at'] = date('Y-m-d H:I:s');
                    $post['updated_at'] = date('Y-m-d H:I:s');
                    $stat = $this->db->insert("posts", $post);
                    if (!$stat) echo '文章=>' . $iterm->title . '  入库失败...' . PHP_EOL;
                    else $i++;
                }

            }
            if ($is_next != null)
                $this->art_url = $this->domain . $is_next;
            else
                break;

        } while (1);
        echo '所有文章已采集入库，本次入库 【' . $i . '】  条记录...' . PHP_EOL;


    }

    private function file2DB()
    {
        $files_list = files_list('/home/luffy/Downloads/关键词采集/百度新闻_微信小程序', 'txt', [], 2);
        foreach ($files_list as $k => $v) {
            $is_insert = $this->db->get("posts", 'id', ["post_title[~]" => $v['filename']]);
            if ($is_insert) {
                echo '文章=>' . $v['filename'] . '  已存在(id=' . $is_insert . ')...' . PHP_EOL;
            } else {
                echo '文章=>' . $v['filename'] . '  正在入库...' . PHP_EOL;
                $post['post_title'] = $v['filename'];
                $content = file_get_contents($v['path']);
                $data = $this->contProces($content, 1);
                $post['cover_image'] = $data['cover'];
                $post['auth_id'] = 0;
                $post['content'] = $data['content'];
                $post['post_type'] = 'article';
                $post['tags'] = 0;
                $post['created_at'] = date('Y-m-d H:i:s');
                $post['updated_at'] = date('Y-m-d H:i:s');
                #var_dump($post);
                $stat = $this->db->insert("posts", $post);
                if (!$stat) echo '文章=>' . $v['filename'] . '  入库失败...' . PHP_EOL;
            }
        }
    }

    /**
     * 文章内容处理[图片转七牛,内容替换]
     * @param $content
     * @param $cover 是否提取图片
     * @return mixed
     */
    private function contProces($content, $cover = 0)
    {
        if (!isutf8($content)) {
            $content = iconv('GB2312', 'UTF-8', $content);
        }
        #$content=get_content_array($content,);
        $data['cover'] = '';
        $preg = '/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i';
        preg_match_all($preg, $content, $imgArr);
        if ($imgArr) {
            foreach ($imgArr[0] as $key => $img) {
                $rep_img = $this->upImg2Qin($imgArr[1][$key], null, 'post');
                if ($cover && $key == $cover)
                    $data['cover'] = $rep_img;
                $content = str_replace($img, '<img src="' . $this->qiniu_url . $rep_img . '" />', $content);
            }
        }
        $data['content'] = $this->preg_rep($content);
        return $data;
    }

    /**
     * 数据替换
     * @param $content
     * @return mixed
     */
    private function preg_rep($content)
    {
        $content = preg_replace('/\\n<p><strong>本文由知晓程序(.*)。<\/strong><\/p>\\n/isU', '', $content);
        $content = preg_replace('/<p>\s+文章来源(.*)<\/p>/isU', '', $content);

        return $content;
    }

    /**
     * 七牛图片上传
     * @param $img_url 图片地址
     * @param null $key 指定key
     * @param string $type 图片目录
     * @return null|string
     */
    private function upImg2Qin($img_url, $key = null, $type = 'icon')
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
}

$caiji = new Caiji();
$caiji->run();
