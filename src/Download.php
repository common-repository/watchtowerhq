<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-12
 * Time: 23:57
 */

namespace WhatArmy\Watchtower;

/**
 * Class Download
 * @package WhatArmy\Watchtower
 */
class Download
{

    /**
     * Download constructor.
     */
    public function __construct()
    {
        add_filter('query_vars', [$this, 'add_query_vars'], 0);
        add_action('parse_request', [$this, 'sniff_requests'], 0);
        add_action('init', [$this, 'add_endpoint'], 0);
    }

    /**
     * @param $vars
     * @return mixed
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'wht_download';
        $vars[] = 'wht_download_finished';
        $vars[] = 'wht_download_big_object';
        $vars[] = 'wht_download_big_object_origin';
        $vars[] = 'wht_download_big_object_offset';
        $vars[] = 'wht_download_big_object_length';
        $vars[] = 'access_token';
        $vars[] = 'backup_name';
        return $vars;
    }

    // Add API Endpoint
    public function add_endpoint()
    {
        add_rewrite_rule('^wht_download/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download=1&access_token=$matches[1]&backup_name=$matches[2]', 'top');
        add_rewrite_rule('^wht_download_finished/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download_finished=1&access_token=$matches[1]&backup_name=$matches[2]', 'top');
        add_rewrite_rule('^wht_download_big_object/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download_big_object=1&access_token=$matches[1]&wht_download_big_object_origin=$matches[2]&wht_download_big_object_offset=$matches[3]&wht_download_big_object_length=$matches[4]', 'top');
    }

    /**
     * @param $token
     * @return bool
     */
    private function has_access($token): bool
    {
        if ($token === get_option('watchtower')['access_token']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function sniff_requests()
    {
        global $wp;
        if (isset($wp->query_vars['wht_download']) || isset($wp->query_vars['wht_download_finished'])) {
            $this->handle_request();
        } else if (isset($wp->query_vars['wht_download_big_object']) && isset($wp->query_vars['wht_download_big_object_origin'])) {
            $this->handle_big_object_download_request();
        }
    }

    public function access_denied_response()
    {
        http_response_code(401);
        header('content-type: application/json; charset=utf-8');
        echo json_encode([
                'status' => 401,
                'message' => 'File not exist or wrong token',
            ]) . "\n";
    }

    public function file_not_exist_response()
    {
        http_response_code(404);
        header('content-type: application/json; charset=utf-8');
        echo json_encode([
                'status' => 404,
                'message' => 'File not exist',
            ]) . "\n";
    }

    public function handle_big_object_download_request()
    {
        global $wp;
        $wp->query_vars['wht_download_big_object_origin'] = wp_unslash($wp->query_vars['wht_download_big_object_origin']);
        $hasAccess = $this->has_access($wp->query_vars['access_token']);
        if ($hasAccess == true) {
            if (file_exists($wp->query_vars['wht_download_big_object_origin'])) {
                if (isset($wp->query_vars['wht_download_big_object_length']) && isset($wp->query_vars['wht_download_big_object_offset'])) {
                    $this->serveObjectFile($wp->query_vars['wht_download_big_object_origin'], $wp->query_vars['wht_download_big_object_offset'], $wp->query_vars['wht_download_big_object_length']);
                } else {
                    $this->serveFile($wp->query_vars['wht_download_big_object_origin']);
                }

            } else {
                $this->file_not_exist_response();
            }
        } else {
            $this->access_denied_response();
        }
        exit;
    }

    /**
     *
     */
    public function handle_request()
    {
        global $wp;
        $hasAccess = $this->has_access($wp->query_vars['access_token']);
        $file = WHTHQ_BACKUP_DIR . '/' . $wp->query_vars['backup_name'];
        if ($hasAccess == true && file_exists($file)) {
            if (isset($wp->query_vars['wht_download_finished'])) {
                unlink($file);
                http_response_code(200);
                header('content-type: application/json; charset=utf-8');
                echo json_encode([
                        'status' => 200,
                        'message' => 'OK',
                    ]) . "\n";
            } else {
                $this->serveFile($file);
            }

        } else {
            $this->access_denied_response();
        }
        exit;
    }

    /**
     * @param $size
     * @param $timestamp
     */
    protected function sendObjectHeaders($size, $timestamp)
    {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $size);
        header('Created-Timestamp: ' . $timestamp);
    }

    /**
     * @param $file
     * @param null $name
     * @param $offset
     */
    protected function sendHeaders($file, $offset, $name = null)
    {
        $mime = (strpos($file, '.zip') !== false) ? 'application/zip' : 'application/gzip';
        if ($name == null) {
            $name = basename($file);
        }
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $name . '";');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (filesize($file) - $offset));
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", filemtime($file)) . " GMT");
        header('Accept-Ranges: bytes');
        if ($offset > 0) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $offset . '-' . (filesize($file) - 1) . '/' . (filesize($file) - 1));
        }
    }

    /**
     * @param $file
     * @return int
     */
    protected function resumeTransferOffset($file)
    {
        if (isset($_SERVER['HTTP_RANGE'])) {
            // if the HTTP_RANGE header is set we're dealing with partial content
            preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
            $offset = intval($matches[1]);
        } else {
            $offset = 0;
        }
        return $offset;
    }

    /**
     * @param $file
     * @param $offset
     * @param $length
     */
    public function serveObjectFile($file, $offset, $length)
    {
        $buffer = file_get_contents($file, FALSE, NULL, $offset, $length);
        self::sendObjectHeaders(strlen($buffer), filemtime($file));
        exit($buffer);
    }

    /**
     * @param $file
     */
    public function serveFile($file)
    {
        $offset = self::resumeTransferOffset($file);
        self::sendHeaders($file, $offset);
        $download_rate = 600 * 10;
        $handle = fopen($file, 'r');
        // seek to the requested offset, this is 0 if it's not a partial content request
        if ($offset > 0) {
            fseek($handle, $offset);
        }
        while (!feof($handle)) {
            $buffer = fread($handle, round($download_rate * 1024));
            echo $buffer;
            if (strpos($file, 'sql.gz') === false) {
                @ob_end_flush();
            }
            flush();
            //use sleep for all non WPE hosting
            if (!function_exists('is_wpe')) {
                sleep(1);
            }
        }
        fclose($handle);
        exit;
    }

}
