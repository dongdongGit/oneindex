<?php
    class oneindex
    {
        public static $dir = [];
        public static $file = [];
        public static $thumb = [];

        //使用 $refresh_token，获取 $access_token
        public static function get_token($refresh_token)
        {
        }

        // 刷新缓存
        public static function refresh_cache($path)
        {
            set_time_limit(0);
            if (php_sapi_name() == 'cli') {
                echo $path . PHP_EOL;
            }
            $items = onedrive::dir($path);
            if (is_array($items)) {
                cache::set('dir_' . $path, $items, config('cache_expire_time'));
            }
            foreach ((array)$items as $item) {
                if ($item['folder']) {
                    self::refresh_cache($path . $item['name'] . '/');
                }
            }
        }

        // 列目录
        public static function dir($path = '/')
        {
            $path = self::get_absolute_path($path);

            if (!empty(self::$dir[$path])) {
                return self::$dir[$path];
            }

            self::$dir[$path] = cache::get('dir_' . $path, function () use ($path) {
                return onedrive::dir($path);
            }, config('cache_expire_time'));
            return self::$dir[$path];
        }

        // 获取文件信息
        public static function file($path)
        {
            $path = self::get_absolute_path($path);
            $path_parts = pathinfo($path);
            $items = self::dir($path_parts['dirname']);
            if (!empty($items) && !empty($items[$path_parts['basename']])) {
                return $items[$path_parts['basename']];
            }
        }

        // 文件是否存在
        public static function file_exists($path)
        {
            if (!empty(self::file($path))) {
                return true;
            }
            return false;
        }

        //获取文件内容
        public static function get_content($path)
        {
            $item = self::file($path);
            // 仅小于10M 获取内容
            if (empty($item) or $item['size'] > 10485760) {
                return false;
            }

            return cache::get('content_' . $item['path'], function () use ($item) {
                $resp = fetch::get($item['downloadUrl']);
                if ($resp->http_code == 200) {
                    return $resp->content;
                }
            }, config('cache_expire_time'));
        }

        //缩略图
        public static function thumb($path, $width = 800, $height = 800)
        {
            $path = self::get_absolute_path($path);
            if (empty(self::$thumb[$path])) {
                self::$thumb[$path] = cache::get('thumb_' . $path, function () use ($path) {
                    $url = onedrive::thumbnail($path);
                    list($url, $tmp) = explode('&width=', $url);
                    return $url;
                }, config('cache_expire_time'));
            }

            self::$thumb[$path] .= strpos(self::$thumb[$path], '?') ? '&' : '?';
            return self::$thumb[$path] . "width={$width}&height={$height}";
        }

        //获取下载链接
        public static function download_url($path)
        {
            $item = self::file($path);
            if (!empty($item['downloadUrl'])) {
                return $item['downloadUrl'];
            }
            return false;
        }

        public static function web_url($path)
        {
            $path = self::get_absolute_path($path);
            $path = rtrim($path, '/');

            if (!empty(config($path . '@weburl'))) {
                return config($path . '@weburl');
            } else {
                $share = onedrive::share($path);
                if (!empty($share['link']['webUrl'])) {
                    config($path . '@weburl', $share['link']['webUrl']);
                    return $share['link']['webUrl'];
                }
            }
        }

        public static function direct_link($path)
        {
            $web_url = self::web_url($path);
            if (!empty($web_url)) {
                $arr = explode('/', $web_url);
                if (strpos($arr[2], 'sharepoint.com') > 0) {
                    $k = array_pop($arr);
                    unset($arr[3], $arr[4]);

                    return join('/', $arr) . '/_layouts/15/download.aspx?share=' . $k;
                } elseif (strpos($arr[2], '1drv.ms') > 0) {
                    // code...
                }
            }
        }

        //工具函数获取绝对路径
        public static function get_absolute_path($path)
        {
            $path = str_replace(['/', '\\', '//'], '/', $path);
            $parts = array_filter(explode('/', $path), 'strlen');
            $absolutes = [];
            foreach ($parts as $part) {
                if ('.' == $part) {
                    continue;
                }
                if ('..' == $part) {
                    array_pop($absolutes);
                } else {
                    $absolutes[] = $part;
                }
            }
            return str_replace('//', '/', '/' . implode('/', $absolutes) . '/');
        }
    }
