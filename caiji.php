<?php
require_once 'function.php';
require_once __DIR__ . '/vendor/autoload.php';
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;

#error_reporting(0);
set_time_limit(0);

class Caiji
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $app_url;
    private $art_url;

    public function __construct()
    {
        $this->accessKey = 'O0lkrGDG8Nel6rr1FbaRqR-efJtKiD6CNwNCUTis';
        $this->secretKey = 'NJSBbCXgaKfkBAaKx406GRiRfHiK2wMrXx4xaALD';
        $this->bucket = 'xiaochengxu';
        $this->domain = 'https://minapp.com';
        $this->app_url = $this->domain . '/api/v3/trochili/miniapp/?limit=9000';
        $this->art_url = $this->domain . '/api/v3/trochili/post/?post_type=article&limit=20&offset=0';
        $this->qiniu_url = 'http://oiyeeqrdv.bkt.clouddn.com/';
        $this->db = new medoo([
            'database_type' => 'mysql',
            'database_name' => 'xiaochengxu',
            'server' => '127.0.0.1',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8'
        ]);
    }

    public function run()
    {
        /*$url = 'http://media.ifanrusercontent.com/media/user_files/trochili/ad/07/ad07087fa027a86abf9402a3534259e6abadb792-3de1c6ad0258927562f01bdef5b470dc475cbbbb.jpg';
        $sat = $this->upImg2Qin($url);
        print_r($sat);*/
        $this->getArticleList();

    }

    private function getApplist()
    {
        $app_list = huoduan_get_html($this->app_url);
        $data_list = json_decode($app_list, 1);

        foreach ($data_list['objects'] as $key => $value) {
            $is_insert = $this->db->get("wxapps", 'id', ["title[~]" => $value['name']]);
            if ($is_insert) {
                echo '应用=>' . $value['name'] . '  已存在(id=' . $is_insert . ')...' . PHP_EOL;
            } else {
                echo '应用=>' . $value['name'] . '  正在入库...' . PHP_EOL;
                $wxapps['user_id'] = 1;
                $wxapps['title'] = $value['name'];
                $wxapps['description'] = $value['description'];
                $wxapps['rating'] = $value['overall_rating'];
                $wxapps['status'] = 1;
                $wxapp_icons['image'] = $this->upImg2Qin($value['icon']['image']);
                $wxapp_qrcodes['image'] = $this->upImg2Qin($value['qrcode']['image'], null, 'qrcode');
                $wxapp_id = $this->db->insert("wxapps", $wxapps);
                foreach ($value['tag'] as $t => $g) {
                    $wxapp_tags[$t]['tag_id'] = $g['id'];
                    $wxapp_tags[$t]['wxapp_id'] = $wxapp_id;
                }
                foreach ($value['screenshot'] as $s => $v) {
                    $wxapp_screenshots[$s]['image'] = $this->upImg2Qin($v['image'], null, 'screenshot');
                    $wxapp_screenshots[$s]['wxapp_id'] = $wxapp_id;
                }
                $wxapp_icons['wxapp_id'] = $wxapp_qrcodes['wxapp_id'] = $wxapp_id;

                $this->db->insert("wxapp_icons", $wxapp_icons);
                $this->db->insert("wxapp_screenshots", $wxapp_screenshots);
                $this->db->insert("wxapp_tags", $wxapp_tags);
                $this->db->insert("wxapp_qrcodes", $wxapp_qrcodes);
            }
        }

    }

    private function getArticleList()
    {
        $i=0;
        do {
            $art_list = huoduan_get_html($this->art_url);
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
                    $post['content'] = $this->contProces($iterm->content);
                    $post['post_type'] = 'article';
                    $post['tags'] = 0;
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

    private function contProces($content)
    {
        #$content=get_content_array($content,);
        $preg = '/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i';
        preg_match_all($preg, $content, $imgArr);
        foreach ($imgArr[0] as $key => $img) {
            $rep_img = $this->upImg2Qin($imgArr[1][$key], null, 'post');
            $content = str_replace($img, '<img src="' . $this->qiniu_url . $rep_img . '" />', $content);
        }
        $content = preg_replace('/\\n<p><strong>本文由知晓程序(.*)。<\/strong><\/p>\\n/isU', '', $content);
        return $content;
    }

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








	