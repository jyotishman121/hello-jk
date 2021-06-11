<?php

namespace TheLion\UseyourDrive;

class Upload
{
    /**
     * @var \TheLion\UseyourDrive\Client
     */
    private $_client;

    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

    /**
     * @var WPC_UploadHandler
     */
    private $upload_handler;

    public function __construct(Processor $_processor = null)
    {
        $this->_client = $_processor->get_client();
        $this->_processor = $_processor;

        // Upload File to server
        if (!class_exists('WPC_UploadHandler')) {
            require 'jquery-file-upload/server/UploadHandler.php';
        }
    }

    public function upload_pre_process()
    {
        do_action('useyourdrive_upload_pre_process', $this->_processor);

        foreach ($_REQUEST['files']  as $hash => $file) {
            if (!empty($file['path'])) {
                $this->create_folder_structure($file['path']);
            }
        }

        $result = ['result' => 1];
        $result = apply_filters('useyourdrive_upload_pre_process_result', $result, $this->_processor);

        echo json_encode($result);
    }

    public function do_upload()
    {
        if ('1' === $this->get_processor()->get_shortcode_option('demo')) {
            // TO DO LOG + FAIL ERROR
            exit(-1);
        }

        $shortcode_max_file_size = $this->get_processor()->get_shortcode_option('maxfilesize');
        $shortcode_min_file_size = $this->get_processor()->get_shortcode_option('minfilesize');
        $accept_file_types = '/.('.$this->get_processor()->get_shortcode_option('upload_ext').')$/i';
        $post_max_size_bytes = min(Helpers::return_bytes(ini_get('post_max_size')), Helpers::return_bytes(ini_get('upload_max_filesize')));
        $max_file_size = ('0' !== $shortcode_max_file_size) ? Helpers::return_bytes($shortcode_max_file_size) : $post_max_size_bytes;
        $min_file_size = (!empty($shortcode_min_file_size)) ? Helpers::return_bytes($shortcode_min_file_size) : -1;
        $use_upload_encryption = ('1' === $this->get_processor()->get_shortcode_option('upload_encryption') && (version_compare(phpversion(), '7.1.0', '<=')));

        $options = [
            'access_control_allow_methods' => ['POST', 'PUT'],
            'accept_file_types' => $accept_file_types,
            'inline_file_types' => '/\.____$/i',
            'orient_image' => false,
            'image_versions' => [],
            'max_file_size' => $max_file_size,
            'min_file_size' => $min_file_size,
            'print_response' => false,
        ];

        $error_messages = [
            1 => __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'wpcloudplugins'),
            2 => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'wpcloudplugins'),
            3 => __('The uploaded file was only partially uploaded', 'wpcloudplugins'),
            4 => __('No file was uploaded', 'wpcloudplugins'),
            6 => __('Missing a temporary folder', 'wpcloudplugins'),
            7 => __('Failed to write file to disk', 'wpcloudplugins'),
            8 => __('A PHP extension stopped the file upload', 'wpcloudplugins'),
            'post_max_size' => __('The uploaded file exceeds the post_max_size directive in php.ini', 'wpcloudplugins'),
            'max_file_size' => __('File is too big', 'wpcloudplugins'),
            'min_file_size' => __('File is too small', 'wpcloudplugins'),
            'accept_file_types' => __('Filetype not allowed', 'wpcloudplugins'),
            'max_number_of_files' => __('Maximum number of files exceeded', 'wpcloudplugins'),
            'max_width' => __('Image exceeds maximum width', 'wpcloudplugins'),
            'min_width' => __('Image requires a minimum width', 'wpcloudplugins'),
            'max_height' => __('Image exceeds maximum height', 'wpcloudplugins'),
            'min_height' => __('Image requires a minimum height', 'wpcloudplugins'),
        ];

        $this->upload_handler = new \WPC_UploadHandler($options, false, $error_messages);
        $response = @$this->upload_handler->post(false);

        // Upload files to Google
        foreach ($response['files'] as &$file) {
            // Set return Object
            $file->listtoken = $this->get_processor()->get_listtoken();
            $file->name = Helpers::filter_filename(stripslashes(rawurldecode($file->name)), false);
            $file->hash = $_POST['hash'];
            $file->path = $_REQUEST['file_path'];
            $file->description = sanitize_textarea_field(wp_unslash($_REQUEST['file_description']));

            // Set Progress
            $return = ['file' => $file, 'status' => ['bytes_up_so_far' => 0, 'total_bytes_up_expected' => $file->size, 'percentage' => 0, 'progress' => 'starting']];
            self::set_upload_progress($file->hash, $return);

            if (isset($file->error)) {
                $file->error = __('Uploading failed', 'wpcloudplugins').': '.$file->error;
                $return['file'] = $file;
                $return['status']['progress'] = 'upload-failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[WP Cloud Plugin message]: '.sprintf('Uploading failed: %s', $file->error));

                exit();
            }

            if ($use_upload_encryption) {
                $return['status']['progress'] = 'encrypting';
                self::set_upload_progress($file->hash, $return);

                $result = $this->do_encryption($file);

                if ($result) {
                    $file->name .= '.aes';
                    clearstatcache();
                    $file->size = filesize($file->tmp_path);
                }
            }

            // Create Folders if needed
            $upload_folder_id = $this->get_processor()->get_last_folder();
            if (!empty($file->path)) {
                $upload_folder_id = $this->create_folder_structure($file->path);
            }

            // Write file
            $chunkSizeBytes = 1 * 1024 * 1024;

            // Update Mime-type if needed (for IE8 and lower?)
            $fileExtension = pathinfo($file->name, PATHINFO_EXTENSION);
            $file->type = Helpers::get_mimetype($fileExtension);

            // Overwrite if needed
            $current_entry_id = false;
            if ('1' === $this->get_processor()->get_shortcode_option('overwrite')) {
                $parent_folder = $this->get_client()->get_folder($upload_folder_id);
                $current_entry = $this->get_client()->get_cache()->get_node_by_name($file->name, $parent_folder['folder']);

                if (!empty($current_entry)) {
                    $current_entry_id = $current_entry->get_id();
                }
            }

            // Create new Google File
            $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
            $googledrive_file->setName($file->name);
            $googledrive_file->setMimeType($file->type);
            $googledrive_file->setDescription($file->description);

            // Convert file if needed
            $file->convert = false;
            if ('1' === $this->get_processor()->get_shortcode_option('convert')) {
                $importformats = $this->get_processor()->get_import_formats();
                $convertformats = $this->get_processor()->get_shortcode_option('convert_formats');
                if ('all' === $convertformats[0] || in_array($file->type, $convertformats)) {
                    if (isset($importformats[$file->type])) {
                        $file->convert = $importformats[$file->type];
                        $filename = pathinfo($file->name, PATHINFO_FILENAME);
                        $googledrive_file->setName($filename);
                    }
                }
            }

            // Call the API with the media upload, defer so it doesn't immediately return.
            $this->get_app()->get_client()->setDefer(true);

            try {
                if (false === $current_entry_id) {
                    $googledrive_file->setParents([$upload_folder_id]);
                    $request = $this->get_app()->get_drive()->files->create($googledrive_file, ['userIp' => $this->get_processor()->get_user_ip(), 'supportsAllDrives' => true, 'enforceSingleParent' => true]);
                } else {
                    $request = $this->get_app()->get_drive()->files->update($current_entry_id, $googledrive_file, ['userIp' => $this->get_processor()->get_user_ip(), 'supportsAllDrives' => true, 'enforceSingleParent' => true]);
                }
            } catch (\Exception $ex) {
                $file->error = __('Not uploaded to the cloud', 'wpcloudplugins').': '.$ex->getMessage();
                $return['status']['progress'] = 'upload-failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[WP Cloud Plugin message]: '.sprintf('Not uploaded to the cloud on line %s: %s', __LINE__, $ex->getMessage()));

                exit();
            }

            // Create a media file upload to represent our upload process.
            $media = new \UYDGoogle_Http_MediaFileUpload(
                $this->get_app()->get_client(),
                $request,
                $file->type,
                null,
                true,
                $chunkSizeBytes
            );

            $filesize = filesize($file->tmp_path);
            $media->setFileSize($filesize);

            /* Start partialy upload
              Upload the various chunks. $status will be false until the process is
              complete. */
            try {
                $upload_status = false;
                $bytesup = 0;
                $handle = fopen($file->tmp_path, 'rb');
                while (!$upload_status && !feof($handle)) {
                    @set_time_limit(60);
                    $chunk = fread($handle, $chunkSizeBytes);
                    $upload_status = $media->nextChunk($chunk);
                    $bytesup += $chunkSizeBytes;

                    // Update progress
                    // Update the progress
                    $status = [
                        'bytes_up_so_far' => $bytesup,
                        'total_bytes_up_expected' => $file->size,
                        'percentage' => (round(($bytesup / $file->size) * 100)),
                        'progress' => 'uploading-to-cloud',
                    ];

                    $current = self::get_upload_progress($file->hash);
                    $current['status'] = $status;
                    self::set_upload_progress($file->hash, $current);
                }

                fclose($handle);
            } catch (\Exception $ex) {
                $file->error = __('Not uploaded to the cloud', 'wpcloudplugins').': '.$ex->getMessage();
                $return['file'] = $file;
                $return['status']['progress'] = 'upload-failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[WP Cloud Plugin message]: '.sprintf('Not uploaded to the cloud on line %s: %s', __LINE__, $ex->getMessage()));

                exit();
            }

            $this->get_app()->get_client()->setDefer(false);

            if (empty($upload_status)) {
                $file->error = __('Not uploaded to the cloud', 'wpcloudplugins');
                $return['file'] = $file;
                $return['status']['progress'] = 'upload-failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[WP Cloud Plugin message]: '.sprintf('Not uploaded to the cloud'));

                exit();
            }

            // check if uploaded file has size
            usleep(500000); // wait a 0.5 sec so Google can create a thumbnail.
            $api_entry = $this->get_app()->get_drive()->files->get($upload_status->getId(), ['userIp' => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsAllDrives' => true]);

            if ((0 === $api_entry->getSize()) && (false === strpos($api_entry->getMimetype(), 'google-apps'))) {
                $deletedentry = $this->get_app()->get_drive()->files->delete($api_entry->getId(), ['userIp' => $this->get_processor()->get_user_ip(), 'supportsAllDrives' => true]);
                $file->error = __('Not succesfully uploaded to the cloud', 'wpcloudplugins');
                $return['status']['progress'] = 'upload-failed';

                return;
            }

            // Add new file to our Cache
            $entry = new Entry($api_entry);
            $cachedentry = $this->get_processor()->get_cache()->add_to_cache($entry);
            $file->completepath = $cachedentry->get_path($this->get_processor()->get_root_folder());
            $file->account_id = $this->get_processor()->get_current_account()->get_id();
            $file->fileid = $cachedentry->get_id();
            $file->filesize = Helpers::bytes_to_size_1024($file->size);
            $file->link = urlencode($cachedentry->get_entry()->get_preview_link());
            $file->folderurl = false;

            foreach ($cachedentry->get_parents() as $parent) {
                $folderurl = $parent->get_entry()->get_preview_link();
                $file->folderurl = urlencode($folderurl);
            }
        }

        $return['file'] = $file;
        $return['status']['progress'] = 'upload-finished';
        $return['status']['percentage'] = '100';
        self::set_upload_progress($file->hash, $return);

        // Create response
        echo json_encode($return);

        exit();
    }

    public function do_upload_direct()
    {
        if ((!isset($_REQUEST['filename'])) || (!isset($_REQUEST['file_size'])) || (!isset($_REQUEST['mimetype']))) {
            exit();
        }

        if ('1' === $this->get_processor()->get_shortcode_option('demo')) {
            echo json_encode(['result' => 0]);

            exit();
        }

        $name = Helpers::filter_filename(stripslashes(rawurldecode($_REQUEST['filename'])), false);
        $size = $_REQUEST['file_size'];
        $path = $_REQUEST['file_path'];
        $mimetype = $_REQUEST['mimetype'];
        $description = sanitize_textarea_field(wp_unslash($_REQUEST['file_description']));

        $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
        $googledrive_file->setName($name);
        $googledrive_file->setMimeType($mimetype);
        $googledrive_file->setDescription($description);

        // Create Folders if needed
        $upload_folder_id = $this->get_processor()->get_last_folder();
        if (!empty($path)) {
            $upload_folder_id = $this->create_folder_structure($path);
        }

        // Convert file if needed
        $convert = false;
        if ('1' === $this->get_processor()->get_shortcode_option('convert')) {
            $importformats = $this->get_processor()->get_import_formats();
            $convert_formats = $this->get_processor()->get_shortcode_option('convert_formats');
            if ('all' === $convert_formats[0] || in_array($mimetype, $convert_formats)) {
                if (isset($importformats[$mimetype])) {
                    $convert = $importformats[$mimetype];
                    $name = pathinfo($name, PATHINFO_FILENAME);
                    $googledrive_file->setName($name);
                }
            }
        }

        // Overwrite if needed
        $current_entry_id = false;
        if ('1' === $this->get_processor()->get_shortcode_option('overwrite')) {
            $parent_folder = $this->get_client()->get_folder($upload_folder_id);
            $current_entry = $this->get_client()->get_cache()->get_node_by_name($name, $parent_folder['folder']);

            if (!empty($current_entry)) {
                $current_entry_id = $current_entry->get_id();
            }
        }

        // Call the API with the media upload, defer so it doesn't immediately return.
        $this->get_app()->get_client()->setDefer(true);
        if (empty($current_entry_id)) {
            $googledrive_file->setParents([$upload_folder_id]);
            $request = $this->get_app()->get_drive()->files->create($googledrive_file, ['userIp' => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsAllDrives' => true, 'enforceSingleParent' => true]);
        } else {
            $request = $this->get_app()->get_drive()->files->update($current_entry_id, $googledrive_file, ['userIp' => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsAllDrives' => true, 'enforceSingleParent' => true]);
        }

        // Create a media file upload to represent our upload process.

        /*    $origin = isset($_SERVER["HTTP_ORIGIN"]) ? $_SERVER["HTTP_ORIGIN"] : null; // REQUIRED FOR CORS LIKE REQUEST (DIRECT UPLOAD)

          $this->get_app()->get_client()->setHttpClient(new \GuzzleHttp\Client(array(
          'verify' => USEYOURDRIVE_ROOTDIR . '/cacerts.pem',
          'headers' => array('Origin' => $origin) */

        $origin = $_REQUEST['orgin'];
        $request_headers = $request->getRequestHeaders();
        $request_headers['Origin'] = $origin;
        $request->setRequestHeaders($request_headers);

        $chunkSizeBytes = 5 * 1024 * 1024;
        $media = new \UYDGoogle_Http_MediaFileUpload(
            $this->get_app()->get_client(),
            $request,
            $mimetype,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize($size);

        try {
            $url = $media->getResumeUri();
            echo json_encode(['result' => 1, 'url' => $url, 'convert' => $convert]);
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Not uploaded to the cloud on line %s: %s', __LINE__, $ex->getMessage()));
            echo json_encode(['result' => 0]);
        }

        exit();
    }

    public static function get_upload_progress($file_hash)
    {
        return get_transient('useyourdrive_upload_'.substr($file_hash, 0, 40));
    }

    public static function set_upload_progress($file_hash, $status)
    {
        // Update progress
        return set_transient('useyourdrive_upload_'.substr($file_hash, 0, 40), $status, HOUR_IN_SECONDS);
    }

    public function get_upload_status()
    {
        $hash = $_REQUEST['hash'];

        // Try to get the upload status of the file
        for ($_try = 1; $_try < 6; ++$_try) {
            $result = self::get_upload_progress($hash);

            if (false !== $result) {
                if ('upload-failed' === $result['status']['progress'] || 'upload-finished' === $result['status']['progress']) {
                    delete_transient('useyourdrive_upload_'.substr($hash, 0, 40));
                }

                break;
            }

            // Wait a moment, perhaps the upload still needs to start
            usleep(500000 * $_try);
        }

        if (false === $result) {
            $result = ['file' => false, 'status' => ['bytes_up_so_far' => 0, 'total_bytes_up_expected' => 0, 'percentage' => 0, 'progress' => 'upload-failed']];
        }

        echo json_encode($result);

        exit();
    }

    public function upload_convert()
    {
        if (!isset($_REQUEST['fileid']) || !isset($_REQUEST['convert'])) {
            exit();
        }
        $file_id = $_REQUEST['fileid'];
        $convert = $_REQUEST['convert'];

        $this->get_processor()->get_cache()->pull_for_changes(null, true);

        $cachedentry = $this->get_client()->get_entry($file_id);
        if (false === $cachedentry) {
            echo json_encode(['result' => 0]);

            exit();
        }

        // If needed convert document. Only possible by copying the file and removing the old one
        try {
            $entry = new \UYDGoogle_Service_Drive_DriveFile();
            $entry->setName($cachedentry->get_entry()->get_basename());
            $entry->setMimeType($convert);
            $api_entry = $this->get_app()->get_drive()->files->copy($cachedentry->get_id(), $entry, ['userIp' => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsAllDrives' => true, 'enforceSingleParent' => true]);

            if (false !== $api_entry && null !== $api_entry) {
                $new_id = $api_entry->getId();
                // Remove file from Cache
                $deleted_entry = $this->get_app()->get_drive()->files->delete($cachedentry->get_id(), ['userIp' => $this->get_processor()->get_user_ip(), 'supportsAllDrives' => true]);
                $this->get_processor()->get_cache()->remove_from_cache($cachedentry->get_id(), 'deleted');
            }
        } catch (\Exception $ex) {
            echo json_encode(['result' => 0]);
            error_log('[WP Cloud Plugin message]: '.sprintf('Upload not converted on Google Drive', $ex->getMessage()));

            exit();
        }

        echo json_encode(['result' => 1, 'fileid' => $new_id]);

        exit();
    }

    public function upload_post_process()
    {
        if ((!isset($_REQUEST['files'])) || 0 === count($_REQUEST['files'])) {
            echo json_encode(['result' => 0]);

            exit();
        }

        // Update the cache to process all changes
        $this->get_processor()->get_cache()->pull_for_changes(null, true);

        $uploaded_files = $_REQUEST['files'];
        $_uploaded_entries = [];

        foreach ($uploaded_files as $file_id) {
            $cachedentry = $this->get_client()->get_entry($file_id, false);

            if (false === $cachedentry) {
                continue;
            }

            // Upload Hook
            $cachedentry = apply_filters('useyourdrive_upload', $cachedentry, $this->_processor);
            $_uploaded_entries[] = $cachedentry;

            do_action('useyourdrive_log_event', 'useyourdrive_uploaded_entry', $cachedentry);
        }

        // Send email if needed
        if (count($_uploaded_entries) > 0) {
            if ('1' === $this->get_processor()->get_shortcode_option('notificationupload')) {
                $this->get_processor()->send_notification_email('upload', $_uploaded_entries);
            }
        }

        // Return information of the files
        $files = [];
        foreach ($_uploaded_entries as $cachedentry) {
            $file = [];
            $file['name'] = $cachedentry->get_entry()->get_name();
            $file['type'] = $cachedentry->get_entry()->get_mimetype();
            $file['description'] = $cachedentry->get_entry()->get_description();
            $file['account_id'] = $this->get_processor()->get_current_account()->get_id();
            $file['completepath'] = $cachedentry->get_path($this->get_processor()->get_root_folder());
            $file['fileid'] = $cachedentry->get_id();
            $file['filesize'] = Helpers::bytes_to_size_1024($cachedentry->get_entry()->get_size());
            $file['link'] = urlencode($cachedentry->get_entry()->get_preview_link());
            $file['folderurl'] = false;

            foreach ($cachedentry->get_parents() as $parent) {
                $folderurl = $parent->get_entry()->get_preview_link();
                $file['folderurl'] = urlencode($folderurl);
            }

            $files[$file['fileid']] = apply_filters('useyourdrive_upload_entry_information', $file, $cachedentry, $this->_processor);
        }

        do_action('useyourdrive_upload_post_process', $_uploaded_entries, $this->_processor);

        // Clear Cached Requests
        CacheRequest::clear_request_cache();

        echo json_encode(['result' => 1, 'files' => $files]);
    }

    public function do_encryption($file)
    {
        $file_location = $file->tmp_path;
        $passphrase = $this->get_processor()->get_shortcode_option('upload_encryption_passphrase');

        try {
            require_once 'encryption/AESCryptFileLib.php';

            require_once 'encryption/aes256/MCryptAES256Implementation.php';
            $encryption = new \MCryptAES256Implementation();

            $lib = new \AESCryptFileLib($encryption);
            $encrypted_file = $lib->encryptFile($file_location, $passphrase, $file_location);
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Upload not encrypted', $ex->getMessage()));

            return false;
        }

        return true;
    }

    public function create_folder_structure($path)
    {
        $folders = explode('/', $path);
        $current_folder_id = $this->get_processor()->get_last_folder();

        foreach ($folders as $key => $name) {
            $current_folder = $this->get_client()->get_folder($current_folder_id);

            if (empty($name)) {
                continue;
            }

            $cached_entry = $this->get_client()->get_cache()->get_node_by_name($name, $current_folder['folder']);

            if ($cached_entry) {
                $current_folder_id = $cached_entry->get_id();

                continue;
            }
            // Update the parent folder to make sure the latest version is loaded
            $this->get_client()->get_cache()->pull_for_changes([$current_folder_id], true, -1);
            $cached_entry = $this->get_client()->get_cache()->get_node_by_name($name, $current_folder['folder']);

            if ($cached_entry) {
                $current_folder_id = $cached_entry->get_id();

                continue;
            }

            try {
                $newfolder = new \UYDGoogle_Service_Drive_DriveFile();
                $newfolder->setName($name);
                $newfolder->setMimeType('application/vnd.google-apps.folder');
                $newfolder->setParents([$current_folder_id]);
                $api_entry = $this->get_app()->get_drive()->files->create($newfolder, ['fields' => $this->get_client()->apifilefields, 'supportsAllDrives' => true, 'userIp' => $this->get_processor()->get_user_ip(), 'enforceSingleParent' => true]);

                // Add new file to our Cache
                $newentry = new Entry($api_entry);
                $cached_entry = $this->get_client()->get_cache()->add_to_cache($newentry);
                do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $cached_entry);
                $this->get_client()->get_cache()->update_cache();
                $current_folder_id = $cached_entry->get_id();
            } catch (\Exception $ex) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Failed to add user folder: %s', $ex->getMessage()));

                return new \WP_Error('broke', __('Failed to add user folder', 'wpcloudplugins'));
            }
        }

        return $current_folder_id;
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
