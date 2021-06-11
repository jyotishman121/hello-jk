<?php

namespace TheLion\UseyourDrive;

class CacheRequest
{
    /**
     * Set after how much time the cached request should be refreshed.
     * In seconds.
     *
     * @var int
     */
    protected $_max_cached_request_age = 1800; // Half hour in seconds

    /**
     * The file name of the requested cache. This will be set in construct.
     *
     * @var string
     */
    private $_cache_name;

    /**
     * Contains the location to the cache file.
     *
     * @var string
     */
    private $_cache_location;

    /**
     * Contains the file handle in case the plugin has to work
     * with a file for unlocking/locking.
     *
     * @var type
     */
    private $_cache_file_handle;

    // Contains the cached response
    private $_requested_response;

    /**
     * Specific identifier for current user.
     * This identifier is used for caching purposes.
     *
     * @var string
     */
    private $_user_identifier;

    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

    public function __construct(Processor $_processor, $request = null)
    {
        if (empty($request)) {
            $request = $_REQUEST;
        }

        $this->_processor = $_processor;
        $this->_user_identifier = $this->_set_user_identifier();
        $encoded = json_encode($request);
        $request_hash = md5($encoded.$this->get_processor()->get_requested_entry());
        $this->_cache_name = 'request_'.Helpers::filter_filename($this->get_processor()->get_current_account()->get_id().'_'.$_processor->get_listtoken(), false).'_'.$request_hash.'_'.$this->get_user_identifier();
        $this->_cache_location = USEYOURDRIVE_CACHEDIR.'/'.$this->get_cache_name().'.cache';

        // Load Cache
        $this->load_cache();
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        return $this->_processor;
    }

    public function get_user_identifier()
    {
        return $this->_user_identifier;
    }

    public function get_cache_name()
    {
        return $this->_cache_name;
    }

    public function get_cache_location()
    {
        return $this->_cache_location;
    }

    public function load_cache()
    {
        $this->_requested_response = $this->_read_local_cache('close');
    }

    public function is_cached()
    {
        // Check if file exists
        $file = $this->get_cache_location();

        if (!file_exists($file)) {
            return false;
        }

        if ((filemtime($this->get_cache_location()) + $this->_max_cached_request_age) < time()) {
            return false;
        }

        if (empty($this->_requested_response)) {
            return false;
        }

        $sorting = $this->get_processor()->get_shortcode_option('sort_field');

        if (!empty($sorting) && 'shuffle' === $sorting) {
            return false;
        }

        return true;
    }

    public function get_cached_response()
    {
        return $this->_requested_response;
    }

    public function add_cached_response($response)
    {
        $this->_requested_response = $response;
        $this->_clean_local_cache();
        $this->_save_local_cache();
    }

    public static function clear_local_cache_for_shortcode($listtoken)
    {
        $file_name = 'request_'.$listtoken;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            if (false === strpos($path->getFilename(), $file_name)) {
                continue;
            }

            try {
                @unlink($path->getPathname());
            } catch (\Exception $ex) {
                continue;
            }
        }
    }

    public static function clear_request_cache()
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            if ($path->isDir()) {
                continue;
            }
            if ('.htaccess' === $path->getFilename()) {
                continue;
            }

            if (false === strpos($path->getFilename(), 'request_')) {
                continue;
            }

            if (!file_exists($path) || !is_writable($path)) {
                continue;
            }

            try {
                @unlink($path->getPathname());
            } catch (\Exception $ex) {
                continue;
            }
        }
    }

    protected function _set_cache_file_handle($handle)
    {
        return $this->_cache_file_handle = $handle;
    }

    protected function _get_cache_file_handle()
    {
        return $this->_cache_file_handle;
    }

    protected function _clean_local_cache()
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            if ($path->isDir()) {
                continue;
            }
            if ('.htaccess' === $path->getFilename()) {
                continue;
            }

            if (false === strpos($path->getFilename(), 'request_')) {
                continue;
            }

            // Some times files are removed before the plugin is able to check the date
            if (!file_exists($path) || !is_writable($path)) {
                continue;
            }

            try {
                if (($path->getMTime() + $this->_max_cached_request_age) <= time()) {
                    @unlink($path->getPathname());
                }
            } catch (\Exception $ex) {
                continue;
            }
        }
    }

    protected function _read_local_cache($close = false)
    {
        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $this->_create_local_lock(LOCK_SH);
        }

        clearstatcache();

        $data = null;
        if (filesize($this->get_cache_location()) > 0) {
            $data = fread($this->_get_cache_file_handle(), filesize($this->get_cache_location()));
        }

        if (false !== $close) {
            $this->_unlock_local_cache();
        }

        if (function_exists('gzdecode') && function_exists('gzencode') && !empty($data)) {
            $data = @gzdecode($data);
        }

        return $data;
    }

    protected function _create_local_lock($type)
    {
        // Check if file exists
        $file = $this->get_cache_location();

        if (!file_exists($file)) {
            @file_put_contents($file, '');

            if (!is_writable($file)) {
                // TODO log error
                //die(sprintf('Cache file (%s) is not writable', $file));
                error_log('[WP Cloud Plugin message]: '.sprintf('Request file (%s) is not writable', $file));

                return null;
            }
        }

        // Check if the file is more than 1 minute old.
        $requires_unlock = ((filemtime($file) + 60) < (time()));

        // Temporarily workaround when flock is disabled. Can cause problems when plugin is used in multiple processes
        if (false !== strpos(ini_get('disable_functions'), 'flock')) {
            $requires_unlock = false;
        }

        // Check if file is already opened and locked in this process
        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $handle = fopen($file, 'c+');
            if (!is_resource($handle)) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Request file (%s) is not writable', $file));

                throw new \Exception(sprintf('Request file (%s) is not writable', $file));
            }
            $this->_set_cache_file_handle($handle);
        }

        @set_time_limit(60);
        if (!flock($this->_get_cache_file_handle(), $type | LOCK_NB)) {
            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that
             * the previous process died and unlock the file manually
             */
            if ($requires_unlock) {
                $this->_unlock_local_cache();
                $handle = fopen($file, 'c+');
                $this->_set_cache_file_handle($handle);
            }
            // Try to lock the file again
            flock($this->_get_cache_file_handle(), LOCK_EX);
        }
        @set_time_limit(60);

        return true;
    }

    protected function _save_local_cache()
    {
        if (!$this->_create_local_lock(LOCK_EX)) {
            return false;
        }

        $data = $this->_requested_response;

        if (function_exists('gzdecode') && function_exists('gzencode') && !empty($data)) {
            $data = gzencode($data);
        }

        ftruncate($this->_get_cache_file_handle(), 0);
        rewind($this->_get_cache_file_handle());

        $result = fwrite($this->_get_cache_file_handle(), $data);

        $this->_unlock_local_cache();

        return true;
    }

    protected function _unlock_local_cache()
    {
        $handle = $this->_get_cache_file_handle();
        if (!empty($handle)) {
            flock($this->_get_cache_file_handle(), LOCK_UN);
            fclose($this->_get_cache_file_handle());
            $this->_set_cache_file_handle(null);
        }

        clearstatcache();

        return true;
    }

    /**
     * Function to create an specific identifier for current user
     * This identifier can be used for caching purposes.
     */
    private function _set_user_identifier()
    {
        $shortcode = $this->get_processor()->get_shortcode();

        if (empty($shortcode)) {
            return false;
        }

        /* $user_specific_actions = array('addfolder', 'addfolder_role', 'delete', 'delete_files_role', 'delete_folders_role', 'download_role', 'move', 'move_files_role', 'move_folders_role', 'rename', 'rename_files_role', 'rename_folders_role', 'upload', 'upload_role', 'view_role', 'view_user_folders_role', 'editdescription', 'editdescription_role');
          $permissions = array();

          foreach ($user_specific_actions as $action) {
          if (strpos($action, 'role') === false) {
          $permissions[$action] = $shortcode[$action] === '1';
          } else {
          $permissions[$action] = Helpers::check_user_role($shortcode[$action]);
          }
          }
          return md5(json_encode($permissions));
         */

        return $this->get_processor()->get_user()->get_permissions_hash();
    }
}
