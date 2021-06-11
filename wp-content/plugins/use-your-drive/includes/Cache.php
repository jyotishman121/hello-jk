<?php

namespace TheLion\UseyourDrive;

class Cache
{
    /**
     * Set after how much time the cached noded should be refreshed.
     * This value can be overwritten by Cloud Service Cache classes
     * Default:  needed for download/thumbnails urls (1 hour?).
     *
     * @var int
     */
    protected $_max_entry_age = 1800;

    /**
     *  @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

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

    /**
     * $_nodes contains all the cached entries that are present
     * in the Cache File or Database.
     *
     * @var \TheLion\UseyourDrive\CacheNode[]
     */
    private $_nodes = [];

    /**
     * ID of the root node.
     *
     * @var string
     */
    private $_root_node_id;

    /**
     * Is set to true when a change has been made in the cache.
     * Forcing the plugin to save the cache when needed.
     *
     * @var bool
     */
    private $_updated = false;

    /**
     * $_last_update contains a timestamp of the latest check
     * for new updates.
     *
     * @var string
     */
    private $_last_check_for_update = [];

    /**
     * $_last_id contains an ID of the latest update check
     * This can be anything (e.g. a File ID or Change ID), it differs per Cloud Service.
     *
     * @var mixed
     */
    private $_last_check_token = [];

    /**
     * How often do we need to poll for changes? (default: 15 minutes)
     * Each Cloud service has its own optimum setting.
     * WARNING: Please don't lower this setting when you are not using your own Apps!!!
     *
     * @var int
     */
    private $_max_change_age = 900;

    public function __construct(Processor $processor)
    {
        $this->_processor = $processor;

        $cache_id = get_current_blog_id();
        if (null !== $processor->get_current_account()) {
            $cache_id = $processor->get_current_account()->get_id();
        }

        $this->_cache_name = Helpers::filter_filename($cache_id, false).'.index';
        $this->_cache_location = USEYOURDRIVE_CACHEDIR.'/'.$this->_cache_name;

        // Load Cache
        $this->load_cache();
    }

    public function __destruct()
    {
        $this->update_cache();
    }

    public function load_cache()
    {
        $cache = $this->_read_local_cache('close');

        if (function_exists('gzdecode')) {
            $cache = @gzdecode($cache);
        }

        // Unserialize the Cache, and reset if it became somehow corrupt
        if (!empty($cache) && !is_array($cache)) {
            $this->_unserialize_cache($cache);
        }

        // Set all Parent and Children
        if (count($this->_nodes) > 0) {
            foreach ($this->_nodes as $id => $node) {
                $this->init_cache_node($node);
            }
        }
    }

    public function init_cache_node($node = [])
    {
        $id = $node['_id'];
        $node = $this->_nodes[$id] = new CacheNode($this, $node);

        if ($node->has_parents()) {
            foreach ($node->get_parents() as $key => $parent) {
                if ($parent instanceof CacheNode) {
                    continue;
                }

                $parent_id = $parent;
                $parent_node = isset($this->_nodes[$parent_id]) ? $this->_nodes[$parent_id] : false;

                if (!($parent_node instanceof CacheNode)) {
                    $parent_node = $this->init_cache_node($parent_node);
                }

                if (false !== $parent_node) {
                    $node->set_parent($parent_node);
                }
            }
        }

        if ($node->has_children()) {
            foreach ($node->get_children() as $key => $child) {
                if ($child instanceof CacheNode) {
                    continue;
                }

                $child_id = $child;
                $child_node = isset($this->_nodes[$child_id]) ? $this->_nodes[$child_id] : false;

                if (!($child_node instanceof CacheNode)) {
                    $child_node = $this->init_cache_node($child_node);
                }

                if (false !== $child_node) {
                    $child_node->set_parent($node);
                }
            }
        }

        return $node;
    }

    public function reset_cache()
    {
        $this->_nodes = [];
        $this->reset_last_check_for_update();
        $this->reset_last_check_token();
        $this->update_cache();

        return true;
    }

    public function update_cache($clear_request_cache = true)
    {
        if ($this->is_updated()) {
            // Clear Cached Requests, not needed if we only pulled for updates without receiving any changes
            if ($clear_request_cache) {
                CacheRequest::clear_local_cache_for_shortcode($this->get_processor()->get_listtoken());
            }

            // Save each loaded folder
            foreach ($this->get_nodes() as $node) {
                if ($node->is_initialized() && $node->is_dir() && $node->is_updated()) {
                    $node->update_cache();
                }
            }

            $saved = $this->_save_local_cache();

            $this->set_updated(false);
        }
    }

    public function is_cached($value, $findby = 'id', $as_parent = false)
    {
        // Find the node by ID/NAME
        $node = null;
        if ('id' === $findby) {
            $node = $this->get_node_by_id($value);
        } elseif ('name' === $findby) {
            $node = $this->get_node_by_name($value);
        }

        // Return if nothing can be found in the cache
        if (empty($node)) {
            return false;
        }

        if (null === $node->get_entry()) {
            return false;
        }

        if (!$as_parent && !$node->is_loaded()) {
            return false;
        }

        // Check if the children of the node are loaded.
        if (!$as_parent && !$node->has_loaded_children()) {
            return false;
        }

        // Check if the requested node is expired
        if (!$as_parent && $node->is_expired()) {
            if ($node->get_entry()->is_dir()) {
                return $this->get_processor()->get_client()->update_expired_folder($node);
            }

            return $this->get_processor()->get_client()->update_expired_entry($node);
        }

        return $node;
    }

    /**
     * @param \TheLion\UseyourDrive\EntryAbstract $entry
     *
     * @return \TheLion\UseyourDrive\CacheNode
     */
    public function add_to_cache(EntryAbstract $entry)
    {
        // Check if entry is present in cache
        $cached_node = $this->get_node_by_id($entry->get_id());

        /* If entry is not yet present in the cache,
         * create a new Node
         */
        if ((false === $cached_node)) {
            $cached_node = $this->add_node($entry);
        }
        $cached_node->set_name($entry->get_name());

        $cached_node->set_updated();
        $this->set_updated();

        // Set new Expire date
        if ($entry->is_file()) {
            $cached_node->set_expired(time() + $this->get_max_entry_age());
        } else {
            $cached_node->set_is_dir();
        }

        // Set new Entry in node
        $cached_node->set_entry($entry);
        $cached_node->set_loaded(true);

        // Set Loaded_Children to true if entry isn't a folder
        if ($entry->is_file()) {
            $cached_node->set_loaded_children(true);
        }

        // If $entry hasn't parents, it is the root or the entry is only shared with the user
        if (!$entry->has_parents()) {
            $cached_node->set_parents_found(true);

            if (false === $entry->is_special_folder()) {
                $cached_node->set_parent($this->get_node_by_id('shared-with-me'));
            }

            $this->set_updated();

            return $cached_node;
        }

        /*
         * If parents of $entry doesn't exist in our cache yet,
         * We need to get it via the API
         */
        $getparents = [];
        foreach ($entry->get_parents() as $parent_id) {
            // In rare occasions, the plugin is receiving a object instead of and ID)
            if ($parent_id instanceof \UYDGoogle_Service_Drive_DriveFile) {
                $parent_id = $parent_id->getId();
            }

            $parent_in_tree = $this->is_cached($parent_id, 'id', 'as_parent');
            if (false === $parent_in_tree) {
                $getparents[] = $parent_id;
            }
        }

        if (count($getparents) > 0) {
            $parents = $this->get_processor()->get_client()->get_multiple_entries($getparents);

            foreach ($parents as $parent) {
                if (!($parent instanceof EntryAbstract)) {
                    $parent = new Entry($parent);
                }

                $this->add_to_cache($parent);
            }
        }

        // Link all parents to $entry.

        foreach ($entry->get_parents() as $parent_id) {
            $parent_in_tree = $this->is_cached($parent_id, 'id', 'as_parent');
            // Parent does already exists in our cache
            if (false !== $parent_in_tree) {
                $cached_node->set_parent($parent_in_tree);
                $parent_in_tree->set_updated();
            }
        }

        $cached_node->set_parents_found(true);

        // If entry is a shortcut, make sure that the original file is also present in cache
        if ($entry->is_shortcut()) {
            $target_id = $entry->get_shortcut_details('targetId');
            $target_mimetype = $entry->get_shortcut_details('targetMimeType');

            $cached_node->set_original_node_id($target_id);

            $shortcut_node = $this->get_node_by_id($target_id);
            if (false === $shortcut_node) {
                ('application/vnd.google-apps.folder' === $target_mimetype) ? $this->get_processor()->get_client()->get_folder($target_id, false) : $this->get_processor()->get_client()->get_entry($target_id, false);
            }
        }

        $this->set_updated();

        // Return the cached Node
        return $cached_node;
    }

    public function remove_from_cache($entry_id, $reason = 'update', $parent_id = false)
    {
        $node = $this->get_node_by_id($entry_id);

        if (false === $node) {
            return false;
        }

        $node->set_updated();

        if ('update' === $reason) {
            $node->remove_parents();
        } elseif ('moved' === $reason) {
            $node->remove_parents();
        } elseif ('deleted' === $reason) {
            $node->remove_parents();
            $node->delete_cache();
            unset($this->_nodes[$entry_id]);
        }

        $this->set_updated();

        return true;
    }

    /**
     * @return bool|\TheLion\UseyourDrive\CacheNode
     */
    public function get_root_node()
    {
        if (0 === count($this->get_nodes())) {
            return false;
        }

        return $this->get_node_by_id($this->get_root_node_id());
    }

    public function get_root_node_id()
    {
        return $this->_root_node_id;
    }

    public function set_root_node_id($id)
    {
        return $this->_root_node_id = $id;
    }

    public function get_node_by_id($id, $loadoninit = true)
    {
        if (!isset($this->_nodes[$id])) {
            return false;
        }

        if ($loadoninit && !$this->_nodes[$id]->is_initialized() && $this->_nodes[$id]->is_dir()) {
            $this->_nodes[$id]->load();
        }

        return $this->_nodes[$id];
    }

    public function get_node_by_name($search_name, $parent = null)
    {
        if (!$this->has_nodes()) {
            return false;
        }

        $search_name = apply_filters('useyourdrive_cache_node_by_name_set_search_name', $search_name, $this);

        /**
         * @var \TheLion\UseyourDrive\CacheNode $node
         */
        foreach ($this->_nodes as $node) {
            $node_name = apply_filters('useyourdrive_cache_node_by_name_set_node_name', $node->get_name(), $this);

            if ($node_name === $search_name) {
                if (null === $parent) {
                    return $node;
                }

                if ($node->is_in_folder($parent->get_id())) {
                    return $node;
                }
            }
        }

        return false;
    }

    public function get_drive_id_by_entry_id($entry_id)
    {
        $node = $this->get_node_by_id($entry_id);

        if (false === $node) {
            return null;
        }

        if (null !== $node->get_drive_id()) {
            return $node->get_drive_id();
        }

        foreach ($node->get_parents() as $parent) {
            $drive_id = $this->get_drive_id_by_entry_id($parent->get_id());

            if (null !== $drive_id) {
                return $drive_id;
            }
        }

        return null;
    }

    public function get_shortcut_nodes_by_id($id)
    {
        $shortcut_nodes = [];
        foreach ($this->_nodes as $node) {
            if ($id === $node->get_original_node_id()) {
                $shortcut_nodes[] = $node;
            }
        }

        return $shortcut_nodes;
    }

    public function has_nodes()
    {
        return count($this->_nodes) > 0;
    }

    /**
     * @return \TheLion\UseyourDrive\CacheNode[]
     */
    public function get_nodes()
    {
        return $this->_nodes;
    }

    public function add_node(EntryAbstract $entry)
    {
        // TODO: Set expire based on Cloud Service
        $cached_node = new CacheNode(
            $this,
            [
                '_id' => $entry->get_id(),
                '_drive_id' => $entry->get_drive_id(),
                '_account_id' => $this->get_processor()->get_current_account()->get_id(),
                '_name' => $entry->get_name(),
                '_initialized' => true,
            ]
        );

        return $this->set_node($cached_node);
    }

    public function set_node(CacheNode $node)
    {
        $id = $node->get_id();
        $this->_nodes[$id] = $node;

        return $this->_nodes[$id];
    }

    public function pull_for_changes($folder_ids = [], $force_update = false, $buffer = 10)
    {
        $force = (defined('FORCE_REFRESH') ? true : $force_update);

        if (empty($folder_ids)) {
            $folder_ids[] = $this->get_processor()->get_shortcode_option('root');
        }

        $drive_ids = [];
        $folder_ids = array_unique($folder_ids);

        foreach ($folder_ids as $folder_id) {
            $drive_ids[] = $this->get_drive_id_by_entry_id($folder_id);
        }

        $drive_ids = array_unique($drive_ids);

        if (false !== array_search('drive', $drive_ids) || false !== array_search(null, $drive_ids)) {
            // Reset the complete Drive cache including shared folders and shared with me
            $this->get_processor()->reset_complete_cache(false);

            return $this->update_cache();
        }

        foreach ($drive_ids as $drive_id) {
            // Check if we need to check for updates
            $current_time = time();
            $update_needed = ($this->get_last_check_for_update($drive_id) + $this->get_max_change_age());
            if (($current_time < $update_needed) && !$force) {
                continue;
            }
            if (true === $force && ($this->get_last_check_for_update($drive_id) > $current_time - $buffer)) { // Don't pull again if the request was within $buffer seconds
                continue;
            }

            $result = $this->get_processor()->get_client()->pull_for_changes($drive_id, $this->get_last_check_token($drive_id));

            if (empty($result)) {
                continue;
            }

            list($new_change_token, $changes) = $result;
            $this->set_last_check_token($drive_id, $new_change_token);
            $this->set_last_check_for_update($drive_id);

            if (is_array($changes) && count($changes) > 0) {
                $result = $this->_process_changes($changes);

                if (!defined('HAS_CHANGES')) {
                    define('HAS_CHANGES', true);
                }

                $this->update_cache();
            }
        }

        $this->update_cache(false);

        return false;
    }

    public function is_updated()
    {
        return $this->_updated;
    }

    public function set_updated($value = true)
    {
        $this->_updated = (bool) $value;

        return $this->_updated;
    }

    public function get_cache_name()
    {
        return $this->_cache_name;
    }

    public function get_cache_type()
    {
        return $this->_cache_type;
    }

    public function get_cache_location()
    {
        return $this->_cache_location;
    }

    public function get_last_check_for_update($drive_id)
    {
        if (!isset($this->_last_check_for_update[$drive_id])) {
            $this->_last_check_for_update[$drive_id] = null;
        }

        return $this->_last_check_for_update[$drive_id];
    }

    public function reset_last_check_for_update()
    {
        $this->_last_check_for_update = [];
        $this->set_updated();
    }

    public function set_last_check_for_update($drive_id)
    {
        $this->_last_check_for_update[$drive_id] = time();
        $this->set_updated();

        return $this->_last_check_for_update[$drive_id];
    }

    public function reset_last_check_token()
    {
        $this->_last_check_token = [];
        $this->set_updated();
    }

    public function get_last_check_token($drive_id)
    {
        if (!isset($this->_last_check_token[$drive_id])) {
            $this->_last_check_token[$drive_id] = null;
        }

        return $this->_last_check_token[$drive_id];
    }

    public function set_last_check_token($drive_id, $token)
    {
        $this->_last_check_token[$drive_id] = $token;

        return $this->_last_check_token[$drive_id];
    }

    public function get_max_entry_age()
    {
        return $this->_max_entry_age;
    }

    public function set_max_entry_age($value)
    {
        return $this->_max_entry_age = $value;
    }

    public function get_max_change_age()
    {
        return $this->_max_change_age;
    }

    public function set_max_change_age($value)
    {
        return $this->_max_change_age = $value;
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        return $this->_processor;
    }

    protected function _read_local_cache($close = false)
    {
        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $this->_create_local_lock(LOCK_SH);
        }

        clearstatcache();
        rewind($this->_get_cache_file_handle());

        $data = null;
        if (filesize($this->get_cache_location()) > 0) {
            $data = fread($this->_get_cache_file_handle(), filesize($this->get_cache_location()));
        }

        if (false !== $close) {
            $this->_unlock_local_cache();
        }

        return $data;
    }

    protected function _create_local_lock($type)
    {
        // Check if file exists
        $file = $this->get_cache_location();

        if (!file_exists($file)) {
            @file_put_contents($file, $this->_serialize_cache());

            if (!is_writable($file)) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Cache file (%s) is not writable', $file));

                exit(sprintf('Cache file (%s) is not writable', $file));
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
                error_log('[WP Cloud Plugin message]: '.sprintf('Cache file (%s) is not writable', $file));

                exit(sprintf('Cache file (%s) is not writable', $file));
            }
            $this->_set_cache_file_handle($handle);
        }

        @set_time_limit(60);

        if (!flock($this->_get_cache_file_handle(), $type)) {
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

        $data = $this->_serialize_cache($this);

        ftruncate($this->_get_cache_file_handle(), 0);
        rewind($this->_get_cache_file_handle());

        $result = fwrite($this->_get_cache_file_handle(), $data);

        $this->_unlock_local_cache();
        $this->set_updated(false);

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

    protected function _set_cache_file_handle($handle)
    {
        return $this->_cache_file_handle = $handle;
    }

    protected function _get_cache_file_handle()
    {
        return $this->_cache_file_handle;
    }

    private function _process_changes($changes = [])
    {
        foreach ($changes as $entry_id => $change) {
            if ('deleted' === $change) {
                $this->remove_from_cache($entry_id, 'deleted');
            } else {
                $this->remove_from_cache($entry_id, 'update');
                // Update cache with new entry
                if ($change instanceof EntryAbstract) {
                    $cached_entry = $this->add_to_cache($change);
                }
            }
        }

        $this->set_updated(true);
    }

    private function _serialize_cache()
    {
        $nodes_index = [];
        foreach ($this->_nodes as $id => $node) {
            $nodes_index[$id] = $node->to_index();
        }

        $data = [
            '_nodes' => $nodes_index,
            '_root_node_id' => $this->_root_node_id,
            '_last_check_token' => $this->_last_check_token,
            '_last_check_for_update' => $this->_last_check_for_update,
        ];

        $data_str = serialize($data);

        if (function_exists('gzencode')) {
            $data_str = gzencode($data_str);
        }

        return $data_str;
    }

    private function _unserialize_cache($data)
    {
        $values = unserialize($data);
        if (false !== $values) {
            foreach ($values as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}
