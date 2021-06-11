<?php

namespace TheLion\UseyourDrive;

class Zip
{
    /**
     * Unique ID.
     */
    public $request_id;

    /**
     * Name of the zip file.
     */
    public $zip_name;
    /**
     * Files that need to be added to ZIP.
     */
    public $files = [];

    /**
     * Folders that need to be created in ZIP.
     */
    public $folders = [];

    /**
     * Number of bytes that are downloaded so far.
     */
    public $bytes_so_far = 0;

    /**
     * Bytes that need to be download in total.
     */
    public $bytes_total = 0;

    public $current_action = 'starting';

    public $current_action_str = '';

    /**
     * @var \TheLion\UseyourDrive\CacheNode[]
     */
    public $entries_downloaded = [];
    /**
     * @var \TheLion\UseyourDrive\Client
     */
    private $_client;

    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

    /**
     * @var \PHPZip\Zip\Stream\ZipStream
     */
    private $_zip_handler;

    public function __construct(Processor $_processor = null, $request_id)
    {
        $this->_client = $_processor->get_client();
        $this->_processor = $_processor;
        $this->request_id = $request_id;
    }

    public function do_zip()
    {
        $this->initialize();
        $this->current_action = 'indexing';
        $this->current_action_str = __('Selecting files...', 'wpcloudplugins');

        $this->index();
        $this->create();

        if (ob_get_level() > 0) {
            ob_end_clean(); // Stop WP from buffering
        }

        $this->add_folders();

        $this->current_action = 'downloading';
        $this->add_files();

        $this->current_action = 'finalizing';
        $this->current_action_str = __('Almost ready', 'wpcloudplugins');
        $this->set_progress();
        $this->finalize();

        $this->current_action = 'finished';
        $this->current_action_str = __('Finished', 'wpcloudplugins');
        $this->set_progress();

        die();
    }

    public function initialize()
    {
        require_once 'PHPZip/autoload.php';

        // Check if file/folder is cached and still valid
        $cachedfolder = $this->get_client()->get_folder();

        if (false === $cachedfolder || false === $cachedfolder['folder']) {
            return new \WP_Error('broke', __("Requested directory isn't allowed",'wpcloudplugins'));
        }

        $folder = $cachedfolder['folder']->get_entry();

        // Check if entry is allowed
        if (!$this->get_processor()->_is_entry_authorized($cachedfolder['folder'])) {
            return new \WP_Error('broke', __("Requested directory isn't allowed",'wpcloudplugins'));
        }

        $this->zip_name = '_zip_'.basename($folder->get_name()).'_'.uniqid().'.zip';

        $this->set_progress();
    }

    public function create()
    {
        $this->_zip_handler = new \PHPZip\Zip\Stream\ZipStream(\TheLion\UseyourDrive\Helpers::filter_filename($this->zip_name));
    }

    public function index()
    {
        $requested_ids = [$this->get_processor()->get_requested_entry()];

        if (isset($_REQUEST['files'])) {
            $requested_ids = $_REQUEST['files'];
        }

        foreach ($requested_ids as $fileid) {
            $cached_entry = $this->get_client()->get_entry($fileid);

            if (false === $cached_entry) {
                continue;
            }

            $data = $this->get_client()->_get_files_recursive($cached_entry, '', true);

            $this->files = array_merge($this->files, $data['files']);
            $this->folders = array_merge($this->folders, $data['folders']);
            $this->bytes_total += $data['bytes_total'];

            $this->current_action_str = __('Selecting files...', 'wpcloudplugins').' ('.count($this->files).')';
            $this->set_progress();
        }
    }

    public function add_folders()
    {
        if (count($this->folders) > 0) {
            foreach ($this->folders as $key => $folder) {
                $this->_zip_handler->addDirectory($folder);
                unset($this->folders[$key]);
            }
        }

        flush();
    }

    public function add_files()
    {
        if (count($this->files) > 0) {
            foreach ($this->files as $key => $file) {
                $this->add_file_to_zip($file);
                flush();

                unset($this->files[$key]);

                $cachedentry = $this->get_client()->get_cache()->get_node_by_id($file['ID']);
                $this->entries_downloaded[] = $cachedentry;

                do_action('useyourdrive_log_event', 'useyourdrive_downloaded_entry', $cachedentry, ['as_zip' => true]);

                $this->current_action_str = __('Downloading...', 'wpcloudplugins').'<br/>('.Helpers::bytes_to_size_1024($this->bytes_so_far).' / '.Helpers::bytes_to_size_1024($this->bytes_total).')';
                $this->set_progress();
            }
        }

        flush();
    }

    public function add_file_to_zip($file)
    {
        @set_time_limit(0);

        // get file
        $cachedentry = $this->get_client()->get_cache()->get_node_by_id($file['ID']);
        $download_stream = fopen('php://temp/maxmemory:'.(5 * 1024 * 1024), 'r+');

        $request = new \UYDGoogle_Http_Request($file['url'], 'GET');
        $this->get_client()->get_library()->getIo()->setOptions(
            [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FILE => $download_stream,
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => 900,
                CURLOPT_TIMEOUT => 900,
            ]
        );

        try {
            $this->get_client()->get_library()->getAuth()->authenticatedRequest($request);

            curl_close($this->get_client()->get_library()->getIo()->getHandler());

            /* NEW SDK
             * $httpClient = new \GuzzleHttp\Client(array('verify' => false, 'allow_redirects' => true));
             * $request = new \GuzzleHttp\Psr7\Request('GET', $file['url']);
             * $httpRequest = $httpClient->send($request);
             */
        } catch (\Exception $ex) {
            fclose($download_stream);
            error_log('[WP Cloud Plugin message]: '.sprintf('API Error on line %s: %s', __LINE__, $ex->getMessage()));

            return;
        }

        rewind($download_stream);

        $this->bytes_so_far += $file['bytes'];

        try {
            $this->_zip_handler->addLargeFile($download_stream, $file['path'], $cachedentry->get_entry()->get_last_edited(), $cachedentry->get_entry()->get_description());
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Error creating ZIP file %s: %s', __LINE__, $ex->getMessage()));

            $this->current_action = 'failed';
            $this->set_progress();

            die();
        }

        fclose($download_stream);
    }

    public function finalize()
    {
        $this->set_progress();

        // Close zip
        $result = $this->_zip_handler->finalize();

        // Send email if needed
        if ('1' === $this->get_processor()->get_shortcode_option('notificationdownload')) {
            $this->get_processor()->send_notification_email('download', $this->entries_downloaded);
        }

        // Download Zip Hook
        do_action('useyourdrive_download_zip', $this->entries_downloaded);
    }

    public static function get_progress($request_id)
    {
        return get_transient('useyourdrive_zip_'.substr($request_id, 0, 40));
    }

    public function set_progress()
    {
        $status = [
            'id' => $this->request_id,
            'status' => [
                'bytes_so_far' => $this->bytes_so_far,
                'bytes_total' => $this->bytes_total,
                'percentage' => ($this->bytes_total > 0) ? (round(($this->bytes_so_far / $this->bytes_total) * 100)) : 0,
                'progress' => $this->current_action,
                'progress_str' => $this->current_action_str,
            ],
        ];

        // Update progress
        return set_transient('useyourdrive_zip_'.substr($this->request_id, 0, 40), $status, HOUR_IN_SECONDS);
    }

    public static function get_status($request_id)
    {
        // Try to get the upload status of the file
        for ($_try = 1; $_try < 6; ++$_try) {
            $result = self::get_progress($request_id);

            if (false !== $result) {
                if ('failed' === $result['status']['progress'] || 'finished' === $result['status']['progress']) {
                    delete_transient('useyourdrive_zip_'.substr($request_id, 0, 40));
                }

                break;
            }

            // Wait a moment, perhaps the upload still needs to start
            usleep(500000 * $_try);
        }

        if (false === $result) {
            $result = ['file' => false, 'status' => ['bytes_down_so_far' => 0, 'total_bytes_down_expected' => 0, 'percentage' => 0, 'progress' => 'failed']];
        }

        echo json_encode($result);
        die();
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        return $this->_processor;
    }

    /**
     * @return \TheLion\UseyourDrive\Client
     */
    public function get_client()
    {
        return $this->_client;
    }

    /**
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app()
    {
        return $this->get_processor()->get_app();
    }
}
