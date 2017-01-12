<?php

/**
 * author         : luffy<luffy@comicool.cn>
 * creatdate    : 17-1-9 下午11:02
 * description :
 */

require_once 'init.php';

class Minapp extends BaseSpider
{

    public function __construct()
    {
        parent::__construct();
        $this->logfile = 'Liebaoapp' . date('Y-m-d') . '.log';
        $this->domain = 'http://ent.duba.com';
        $this->spider_url = $this->domain . '/api/wxapp/config.json';
        $this->host = 'ent.duba.com';
        $this->ref = 'http://www.duba.com/wxapp/search.html';
    }

    public function run()
    {
        $urls = $this->getApp($this->user_id);
        if (count($urls)) {
            $rs = $this->postBaidu($urls);
            $this->debug("百度提交返回信息：\r\n" . $rs);
        }
    }

    public function down_img($url, $filepath, $host, $ref = '')
    {
        $ip = rand_ip();
        $host = 'img.cmcmcdn.com';
        $ref = '';
        $header = [
            'Host' => $host,
            'Referer' => $ref,
            'CLIENT-IP' => $ip,
            'X-FORWARDED-FOR' => $ip,
        ];
        foreach ($header as $n => $v) {
            $headerArr[] = $n . ':' . $v;
        }
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
        $content = curl_exec($ch);
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
     * 采集猎豹app列表
     */
    private function getApp($user_id)
    {
        $i = 0;
        $post_urls = array();

        $app_list = http_request($this->spider_url, [], [], $this->host, $this->ref);
        $app_list = get_content_array($app_list, 'var AppMap = ', ']}]', 3);
        $data_list = json_decode($app_list[0], 1);

        foreach (array_reverse($data_list) as $key => $value) {
            _pushMsg('应用=> ' . $value['name'] . '  正在入库...', 0);
            $wxapps['user_id'] = $user_id;
            $wxapps['title'] = $value['name'];
            $wxapps['description'] = $value['sum'];
            $wxapps['source'] = 'liebao';
            $wxapps['source_id'] = $value['id'];
            $wxapps['icon'] = $this->upImg2Qin('http://img.cmcmcdn.com/' . $value['logoUrl']);
            $wxapps['qrcode'] = $this->upImg2Qin('http://img.cmcmcdn.com/' . $value['qrUrl']);
            $wxapps['tags'] = $wxapps['tags'] = trim(array_reduce($value['tags'], function ($ids, $res) {
                return $ids . $res . ',';
            }), ",");;

            $wxapps['screens'] = trim(array_reduce($value['scrUrls'], function ($ids, $res) {
                return $ids . $this->upImg2QinFt($res, null, 'screenshot') . ',';
            }), ",");

            $status = $this->postData('wxapp', $this->token, $wxapps);
            $jsonp = json_decode($status, 1);

            if (isset($jsonp['status']) && $jsonp['status'] == 'success') {
                $stat = $jsonp['status'] . '=>id#' . $jsonp['data']['id'];
                $post_urls[] = $this->basehost . 'xiaochengxu/' . $jsonp['data']['id'] . '/' . $jsonp['data']['name'];
                $i++;
            } else {
                $stat = $status;
            }
            _pushMsg(' 状态：' . $stat);
            sleep(2);
            $this->debug('应用=> ' . $value['name'] . '  正在入库...状态：' . $stat);
            $this->debug('返回', $jsonp);
        }
        _pushMsg('所有应用已采集入库，本次入库 【' . $i . '】  条记录...');
        return $post_urls;

    }
}

$caiji = new Minapp();
$caiji->run();

