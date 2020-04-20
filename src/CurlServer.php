<?php

namespace ChrisComposer\Curl;


class CurlServer
{
    // get 请求
    public function curl_get_https($url)
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 设置：请求的 url
        curl_setopt($curl, CURLOPT_HEADER, 0); // 设置：显示返回的 Header 区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 设置：获取的信息以文件流的形式返回（即存入变量），而不是直接输出。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查 SSL 加密算法是否存在
        $output = curl_exec($curl); // 返回 api 的 json 对象

        curl_close($curl); // 关闭 URL 请求

        return $output; // 返回 json 对象
    }

    /**
     * post 请求
     *
     * @param $url
     * @param array $headers
     * @param string $post
     * @return bool|string
     */
    public function curl_post_https($url, $headers = [], $post = '', $is_response_header = false, $is_get_header_token = false)
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 设置：请求的 url
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的POST请求，类型为：application/x-www-form-urlencoded，就像表单提交的一样。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 是否证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
        //        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        //        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        //        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置 Referer

        // #### 若有 post
        if ($post) { // yes
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // Post 提交的数据包
        }
        // #### 若有请求 header
        if ($headers) { // yes
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        // #### 若需要返回 header
        if ($is_response_header) {
            curl_setopt($curl, CURLOPT_HEADER, $is_response_header); // 将 header 信息作为数据流输出。
        }

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5); // 在发起连接前等待的时间，如果设置为0，则无限等待。单位：秒
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 	设置cURL允许执行的最长秒数。设置超时限制防止死循环

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回（即存入变量），而不是直接输出。，
        $output_data = curl_exec($curl); // 执行操作

        // ### 若不需要返回 响应 header
        if (! $is_response_header) {
            $output['data'] = json_decode($output_data, true);
        }

        // ### 若需要返回 响应 header，且需要从 header 中 get token
        if ($is_response_header && $is_get_header_token) {
            $output = $this->getHeaderMain($curl, $output_data);
        }

        if (curl_errno($curl)) { // 返回错误号或 0 (零) 如果没有错误发生
            $output = '';
        }

        curl_close($curl); // 关闭CURL会话

        return $output; // 返回数据，json 格式
    }

    // 异步多请求
    public function multiple_threads_request_post($data, $is_json = 1)
    {
        // #### check
        $response = array();
        if (empty($data)) {
            return $response;
        }

        $mh = curl_multi_init();

        // #### 创建：批处理句柄
        foreach ($data as $k => $item) {
            $url = $item['url'];
            $post = $item['post'];

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查 SSL 加密算法是否存在

            if ($post) {
                if ($is_json) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($post)));
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // Post 提交的数据包
            }

            curl_multi_add_handle($mh, $ch); // 向 curl 批处理会话中添加单独的 curl 句柄。
        }

        // #### 创建：批处理句柄
        do {
            if (($status = curl_multi_exec($mh, $active)) != CURLM_CALL_MULTI_PERFORM) {
                // CURLM_OK 说明已经有需要处理的数据。这时你需要进行相关处理，处理完后再次调用 curl_multi_exec。
                if ($status != CURLM_OK) {
                    break;
                }

                while ($done = curl_multi_info_read($mh)) { // 获取：当前解析的 cURL 的相关传输信息，handle : cURL资源类型表明它有关的句柄。
                    $result = json_decode(curl_multi_getcontent($done["handle"]), true); // 获取：返回输出的文本流。如果设置了 CURLOPT_RETURNTRANSFER

                    // ### 收集响应
                    $response[] = $result;
                    curl_multi_remove_handle($mh, $done['handle']); // 移除：curl 批处理句柄资源中的某个句柄资源。
                    curl_close($done['handle']); // 关闭：单个请求

                    // 如果仍然有未处理完毕的句柄，那么就 select 等待他相应
                    if ($active > 0) {
                        curl_multi_select($mh, 0.5); // timeout : 以秒为单位，等待响应的时间。
                    }
                }
            }
        } while ($active > 0 || $status == CURLM_CALL_MULTI_PERFORM); // 表示句柄批处理请求还在进行中

        curl_multi_close($mh); // 关闭一组 cURL 句柄。

        return $response;
    }

    public function multiple_threads_request_post_upgrade($data = [])
    {
        // #### check
        $response = array();
        if (empty($data)) {
            return $response;
        }

        $mh = curl_multi_init();

        // #### 创建：批处理句柄
        foreach ($data as $k => $item) {
            // ### define params
            $url = $item['url'];
            $headers = $item['headers'];
            $post = $item['post'];

            // ### set curl
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0); // 是否需要返回相应 header
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查 SSL 加密算法是否存在

            // ## 若有请求 header
            if ($headers) { // yes
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }

            // ## 若有 post
            if ($post) { // yes
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // Post 提交的数据包
            }

            // ### 向 curl 批处理会话中添加单独的 curl 句柄。
            curl_multi_add_handle($mh, $ch);
        }

        // #### exec：批处理句柄
        do {
            if (($status = curl_multi_exec($mh, $active)) != CURLM_CALL_MULTI_PERFORM) {
                // CURLM_OK 说明已经有需要处理的数据。这时你需要进行相关处理，处理完后再次调用 curl_multi_exec。
                if ($status != CURLM_OK) {
                    break;
                }

                // 获取：当前解析的 cURL 的相关传输信息，handle : cURL 资源类型表明当前的 curl 句柄。
                /**
                 * curl_multi_info_read
                 * 查询批处理句柄是否单独的传输线程中有消息或信息返回。
                 * 消息可能包含诸如从单独的传输线程返回的错误码或者只是传输线程有没有完成之类的报告。
                 * 重复调用这个函数，它每次都会返回一个新的结果，直到这时没有更多信息返回时，FALSE 被当作一个信号返回。
                 * 通过 msgs_in_queue 返回的整数指出将会包含当这次函数被调用后，还剩余的消息数。
                 */
                while ($done = curl_multi_info_read($mh)) {
                    // ### get 返回输出的文本流。如果设置了 CURLOPT_RETURNTRANSFER
                    $result = json_decode(curl_multi_getcontent($done["handle"]), true);

                    // ### 收集响应
                    $response[] = $result;
                    // ### 移除：完成的句柄资源。
                    curl_multi_remove_handle($mh, $done['handle']);
                    // ### 关闭：单个请求
                    curl_close($done['handle']);

                    // ### 若仍然有未处理完毕的句柄，那么就 select 等待他相应
                    if ($active > 0) {
                        /**
                         * curl_multi_select
                         * 等待所有cURL批处理中的活动连接
                         */
                        curl_multi_select($mh, 30); // timeout : 以秒为单位，等待响应的时间。
                    }
                }
            }
        } while ($active > 0 || $status == CURLM_CALL_MULTI_PERFORM); // 表示句柄批处理请求还在进行中

        // #### 关闭一组 cURL 句柄。
        curl_multi_close($mh);

        return $response;
    }

    protected function getHeaderMain($curl, $output) {
        // #### get header
        // 获得响应结果里的：头大小
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        // 根据头大小去获取头信息内容
        $header = substr($output, 0, $headerSize);

        // ### get header token
        $index_token = strpos($header, '_token');
        $index_ETag = strpos($header, 'ETag');
        $start = $index_token + 8; // 6 + 2, 2 个 ": "
        $len = $index_ETag - $start;
        $token = str_replace(PHP_EOL, '', substr($header, $start, $len));

        // #### get main
        $main = substr($output, $headerSize);

        return [
            'token' => $token,
            'data' => json_decode($main, true)
        ];
    }
}