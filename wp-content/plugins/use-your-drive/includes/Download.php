<?php

namespace TheLion\UseyourDrive;

class Download
{
    /**
     * @var \TheLion\UseyourDrive\CacheNode
     */
    public $cached_node;

    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

    /**
     * @var string 'proxy'|'redirect'
     */
    private $_download_method;

    /**
     * Should the file be streamed using the server as middleman.
     *
     * @var bool
     */
    private $_force_proxy;

    /**
     * Url to the content of the file. Is only public when file is shared.
     *
     * @var string
     */
    private $_content_url;

    /**
     * Mimetype of the download.
     *
     * @var string
     */
    private $_mimetype;

    /**
     * Extension of the file.
     *
     * @var string
     */
    private $_extension;

    /**
     * Is the download streamed (for media files).
     *
     * @var bool
     */
    private $_is_stream = false;

    public function __construct(CacheNode $cached_node, Processor $_processor = null, $mimetype = 'default', $force_proxy = false)
    {
        $this->_processor = $_processor;
        $this->cached_node = $cached_node;
        $this->_force_proxy = $force_proxy;

        $this->_mimetype = (isset($_REQUEST['mimetype'])) ? $_REQUEST['mimetype'] : $mimetype;
        $this->_extension = isset($_REQUEST['extension']) ? $_REQUEST['extension'] : $this->get_entry()->get_extension();
        $this->_is_stream = (isset($_REQUEST['action']) && 'useyourdrive-stream' === $_REQUEST['action']);

        $this->_set_content_url();
    }

    public function start_download()
    {
        $this->_set_download_method();

        // Execute download Hook
        do_action('useyourdrive_download', $this->get_cached_node(), $this);

        // Log Download
        if ('default' === $this->_mimetype) {
            $event_type = $this->is_stream() ? 'useyourdrive_streamed_entry' : 'useyourdrive_downloaded_entry';

            if ('useyourdrive-stream' === $event_type && 'vtt' === $this->get_entry()->get_extension()) {
                // Don't log VTT captions when requested for video stream
            } else {
                do_action('useyourdrive_log_event', $event_type, $this->get_cached_node());
            }
        } else {
            do_action('useyourdrive_log_event', 'useyourdrive_downloaded_entry', $this->get_cached_node(), ['exported' => strtoupper($this->get_extension())]);
        }

        // Send email if needed
        if ('1' === $this->get_processor()->get_shortcode_option('notificationdownload')) {
            $this->get_processor()->send_notification_email('download', [$this->get_cached_node()]);
        }

        // Finally, start the download
        $this->_process_download();
    }

    public function redirect_to_content()
    {
        // Check if redirect url is still present in database and redirect user
        $transient_name = ($this->is_stream() ? 'useyourdrive_stream' : 'useyourdrive_download').$this->get_cached_node()->get_id().'_'.$this->get_extension();
        $stored_url = get_transient($transient_name);
        if (false !== $stored_url && filter_var($stored_url, FILTER_VALIDATE_URL)) {
            header('Location: '.$stored_url);

            exit();
        }
        if ('force_proxy' === $stored_url) {
            // The URL currently isn't accesible by the plugin. Stream the content instead
            $this->set_force_proxy(true);
            $this->_set_download_method();
            $this->_process_download();

            exit();
        }

        // Get the download link via the webContentLink

        // Redirect User to download URL directly if size <= 24 MB
        if (false === $this->is_stream() && $this->get_entry()->get_size() <= 25165824) {
            header('Location: '.$this->get_content_url());

            exit();
        }

        // Else do some magic to obtain a direct download link
        $request = new \UYDGoogle_Http_Request($this->get_content_url(), 'GET');
        $this->get_client()->get_library()->getIo()->setOptions([CURLOPT_FOLLOWLOCATION => 0]);
        $httpRequest = $this->get_client()->get_library()->getIo()->makeRequest($request);
        $headers = $httpRequest->getResponseHeaders();

        // If the file meets a virus scan warning, do another request
        if (isset($headers['set-cookie']) && false !== strpos($headers['set-cookie'], 'download_warning')) {
            preg_match('/download_warning.*=(.*);/iU', $headers['set-cookie'], $confirm);
            $new_download_link = $this->get_content_url().'&confirm='.$confirm[1];

            $request = new \UYDGoogle_Http_Request($new_download_link, 'HEAD', ['Cookie' => $headers['set-cookie']]);
            $this->get_client()->get_library()->getIo()->setOptions([CURLOPT_FOLLOWLOCATION => 0, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_NOBODY => true]);
            curl_close($this->get_client()->get_library()->getIo()->getHandler());

            usleep(500000);

            $httpRequest = $this->get_client()->get_library()->getIo()->makeRequest($request);
            $headers = $httpRequest->getResponseHeaders();
        }

        if (isset($headers['location'])) {
            header('Location: '.$headers['location']);
            set_transient($transient_name, $headers['location'], MINUTE_IN_SECONDS * 4);
        } else {
            // If we end up here, the request probably isn't accepted by Google.
            // In that case, stream the content.
            error_log('[WP Cloud Plugin message]: '.sprintf('Google Error on line %s: Download redirect for %s denied by Google. Plugin will stream the content instead.', __LINE__, $this->get_cached_node()->get_id()));

            set_transient($transient_name, 'force_proxy', 6 * HOUR_IN_SECONDS);
            $this->set_force_proxy(true);
            $this->_set_download_method();
            $this->_process_download();

            exit();

//            foreach ($headers as $key => $header) {
//                if ($key === 'transfer-encoding') {
//                    continue;
//                }
//
//                if (is_array($header)) {
//                    header("$key: " . implode(' ', $header));
//                } else {
//                    header("$key: " . str_replace("\n", ' ', $header));
//                }
//            }
        }

        echo $httpRequest->getResponseBody();

        exit();
    }

    public function redirect_to_export()
    {
        header('Location: '.$this->get_content_url());

        exit();
    }

    public function download_content()
    {
        if (ob_get_level() > 0) {
            ob_end_clean(); // Stop WP from buffering
        }

        $size = $this->_stream_start();

        if (false === $this->is_stream()) {
            $filename = $this->get_cached_node()->get_name();
            header('Content-Disposition: attachment; '.sprintf('filename="%s"; ', rawurlencode($filename)).sprintf("filename*=utf-8''%s", rawurlencode($filename)));
        }

        flush();

        $i = 0;
        $chunk_size = min(Helpers::get_free_memory_available() - (1024 * 1024 * 5), 1024 * 1024 * 50); // Chunks of 50MB or less if memory isn't sufficient

        if ($size <= 0) {
            $this->_stream_get_chunk(0, '');
        } else {
            while ($i <= $size) {
                set_time_limit(90);

                //Output the chunk
                $this->_stream_get_chunk(((0 == $i) ? $i : $i + 1), ((($i + $chunk_size) > $size) ? $size : $i + $chunk_size));
                $i = ($i + $chunk_size);
            }
        }
    }

    public function stream_content()
    {
        if (ob_get_level() > 0) {
            ob_end_clean(); // Stop WP from buffering
        }

        $chunk_size = min(Helpers::get_free_memory_available() - (1024 * 1024 * 5), 1024 * 1024 * 50); // Chunks of 50MB or less if memory isn't sufficient

        $size = $this->get_cached_node()->get_entry()->get_size();

        $length = $size;           // Content length
        $start = 0;               // Start byte
        $end = $size - 1;       // End byte
        header("Accept-Ranges: 0-{$size}");
        header('Content-Type: '.$this->get_cached_node()->get_entry()->get_mimetype());

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            if (false !== strpos($range, ',')) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes {$start}-{$end}/{$size}");

                exit;
            }

            if ('-' == $range) {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = (int) $range[0];

                if (isset($range[1]) && is_numeric($range[1])) {
                    $c_end = (int) $range[1];
                } else {
                    $c_end = $size;
                }

                if ($c_end - $c_start > $chunk_size) {
                    $c_end = $c_start + $chunk_size;
                }
            }
            $c_end = ($c_end > $end) ? $end : $c_end;

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes {$start}-{$end}/{$size}");

                exit;
            }

            $start = $c_start;

            $end = $c_end;
            $length = $end - $start + 1;
            header('HTTP/1.1 206 Partial Content');
        }

        header("Content-Range: bytes {$start}-{$end}/{$size}");
        header('Content-Length: '.$length);

        $chunk_start = $start;

        set_time_limit(0);

        while ($chunk_start <= $end) {
            //Output the chunk
            $chunk_end = ((($chunk_start + $chunk_size) > $end) ? $end : $chunk_start + $chunk_size);
            $this->_stream_get_chunk($chunk_start, $chunk_end);

            $chunk_start = $chunk_end + 1;
        }
    }

    /**
     * Callback function for CURLOPT_WRITEFUNCTION, This is what prints the chunk.
     *
     * @param type $ch
     * @param type $str
     *
     * @return type
     */
    public function _stream_chunk_to_output($ch, $str)
    {
        echo $str;

        return strlen($str);
    }

    /**
     * Exports are generated on the fly via the API, so can't download this in chunks.
     */
    public function export_content()
    {
        if (ob_get_level() > 0) {
            ob_end_clean(); // Stop WP from buffering
        }

        $request = new \UYDGoogle_Http_Request($this->get_api_url(), 'GET');
        $httpRequest = $this->get_client()->get_library()->getAuth()->authenticatedRequest($request);
        $headers = $httpRequest->getResponseHeaders();

        if (isset($headers['location'])) {
            header('Location: '.$headers['location']);
        } else {
            foreach ($headers as $key => $header) {
                if ('transfer-encoding' === $key) {
                    continue;
                }

                if (is_array($header)) {
                    header("{$key}: ".implode(' ', $header));
                } else {
                    header("{$key}: ".str_replace("\n", ' ', $header));
                }
            }
        }

        echo $httpRequest->getResponseBody();
    }

    public function get_api_url()
    {
        if ('default' !== $this->_mimetype) {
            return 'https://www.googleapis.com/drive/v3/files/'.$this->get_cached_node()->get_id().'/export?alt=media&mimeType='.$this->_mimetype.'&userIp='.Helpers::get_user_ip();
        }

        return 'https://www.googleapis.com/drive/v3/files/'.$this->get_cached_node()->get_id().'?alt=media&userIp='.Helpers::get_user_ip();
    }

    public function get_content_url()
    {
        return $this->_content_url;
    }

    public function get_download_method()
    {
        return $this->_download_method;
    }

    public function get_cached_node()
    {
        return $this->cached_node;
    }

    public function get_entry()
    {
        return $this->get_cached_node()->get_entry();
    }

    public function get_mimetype()
    {
        return $this->_mimetype;
    }

    public function get_extension()
    {
        return $this->_extension;
    }

    public function is_stream()
    {
        return $this->_is_stream;
    }

    public function get_force_proxy()
    {
        return $this->_force_proxy;
    }

    public function set_force_proxy($_force_proxy)
    {
        $this->_force_proxy = $_force_proxy;
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        if (empty($this->_processor)) {
            global $UseyourDrive;
            $this->_processor = $UseyourDrive->get_processor();
        }

        return $this->_processor;
    }

    /**
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app()
    {
        return $this->get_processor()->get_app();
    }

    /**
     * @return \TheLion\UseyourDrive\Client
     */
    public function get_client()
    {
        return $this->get_processor()->get_client();
    }

    private function _set_content_url()
    {
        $direct_download_link = $this->get_entry()->get_direct_download_link();

        // Set download URL for binary files
        if ('default' === $this->get_mimetype() && !empty($direct_download_link)) {
            return $this->_content_url = $direct_download_link.'&userIp='.Helpers::get_user_ip();
        }

        // Set download URL for exporting documents with specific mimetype
        if ('default' !== $this->_mimetype) {
            return $this->_content_url = $this->get_entry()->get_export_link($this->_mimetype).'&userIp='.Helpers::get_user_ip();
        }

        // Set download URL for exporting documents without a specific format requested (preferably to PDF)
        $exportlinks = $this->get_entry()->get_save_as();
        $format = isset($exportlinks['PDF']) ? $exportlinks['PDF'] : reset($exportlinks);
        $this->_mimetype = $format['mimetype'];
        $this->_extension = $format['extension'];

        return $this->_content_url = $this->get_entry()->get_export_link($this->_mimetype).'&userIp='.Helpers::get_user_ip();
    }

    /**
     * Set the download method for this entry
     * Files can be streamed using the server as a proxy ('proxy') or
     * the user can be redirected to download url ('redirect').
     *
     * As the Google API doesn't offer temporarily download links,
     * the specific download method depends on several settings
     *
     * @return 'proxy'|'redirect'
     */
    private function _set_download_method()
    {
        // Is plugin forced to use the proxy method via the plugin options?
        if ($this->_force_proxy || 'proxy' === $this->get_processor()->get_setting('download_method')) {
            return $this->_download_method = 'proxy';
        }

        // Is download via shared links prohibitted by API?
        $copy_disabled = $this->get_entry()->get_permission('copyRequiresWriterPermission');
        if ($copy_disabled) {
            return $this->_download_method = 'proxy';
        }

        // Is file already shared ?
        $is_shared = $this->get_client()->has_permission($this->get_cached_node());
        if ($is_shared) {
            return $this->_download_method = 'redirect';
        }

        // Can the sharing permissions of the file be updated via the plugin?
        $can_update_permissions = ('Yes' === $this->get_processor()->get_setting('manage_permissions')) && $this->get_entry()->get_permission('canshare');
        if (false === $can_update_permissions) {
            return $this->_download_method = 'proxy';
        }

        // Update the Sharing Permissions
        $is_sharing_permission_updated = $this->get_client()->set_permission($this->get_cached_node());
        if (false === $is_sharing_permission_updated) {
            return $this->_download_method = 'proxy';
        }

        return $this->_download_method = 'redirect';
    }

    private function _process_download()
    {
        switch ($this->get_download_method()) {
            case 'proxy':
                if ('default' === $this->_mimetype) {
                    if (isset($_REQUEST['action']) && 'useyourdrive-stream' === $_REQUEST['action']) {
                        $this->stream_content();
                    } else {
                        $this->download_content();
                    }
                } else {
                    $this->export_content();
                }

                break;

            case 'redirect':
                if ('default' === $this->_mimetype) {
                    $this->redirect_to_content();
                } else {
                    $this->redirect_to_export();
                }

                break;
        }

        exit();
    }

    /**
     * Start the stream, set all the Headers and return the size of stream.
     *
     * @param type  $url
     * @param mixed $download
     *
     * @return type
     */
    private function _stream_start($download = true)
    {
        $request = new \UYDGoogle_Http_Request($this->get_api_url(), 'GET');
        $request->disableGzip();

        $this->get_client()->get_library()->getIo()->setOptions(
            [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_RANGE => 0 .'-',
            ]
        );
        $httpRequest = $this->get_client()->get_library()->getAuth()->authenticatedRequest($request);

        $headers = $httpRequest->getResponseHeaders();

        foreach ($headers as $key => $header) {
            if ('transfer-encoding' === $key) {
                continue;
            }

            if ('content-length:' === $key && false === $download) {
                continue;
            }

            if ('content-range:' === $key && false === $download) {
                continue;
            }

            if (is_array($header)) {
                header("{$key}: ".implode(' ', $header));
            } else {
                header("{$key}: ".str_replace("\n", ' ', $header));
            }
        }

        if (isset($headers['content-length'])) {
            return (int) $headers['content-length'];
        }

        return -1;
    }

    /**
     * Function to get a range of bytes via the API.
     *
     * @param type $file
     * @param type $start
     * @param type $end
     */
    private function _stream_get_chunk($start, $end)
    {
        $request = new \UYDGoogle_Http_Request($this->get_api_url(), 'GET', ['Range' => 'bytes='.$start.'-'.$end]);
        $request->disableGzip();

        $this->get_client()->get_library()->getIo()->setOptions(
            [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RANGE => null,
                CURLOPT_NOBODY => null,
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => [$this, '_stream_chunk_to_output'],
                CURLOPT_CONNECTTIMEOUT => null,
                CURLOPT_TIMEOUT => null,
            ]
        );

        $this->get_client()->get_library()->getAuth()->authenticatedRequest($request);
    }
}
