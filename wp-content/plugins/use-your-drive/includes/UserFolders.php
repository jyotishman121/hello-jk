<?php

namespace TheLion\UseyourDrive;

class UserFolders
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
     * @var string
     */
    private $_user_name_template;

    /**
     * @var string
     */
    private $_user_folder_name;

    /**
     * @var \TheLion\UseyourDrive\Entry
     */
    private $_user_folder_entry;

    public function __construct(Processor $_processor = null)
    {
        $this->_client = $_processor->get_client();
        $this->_processor = $_processor;
        $this->_user_name_template = $this->get_processor()->get_setting('userfolder_name');

        $shortcode = $this->get_processor()->get_shortcode();
        if (!empty($shortcode) && !empty($shortcode['user_folder_name_template'])) {
            $this->_user_name_template = $shortcode['user_folder_name_template'];
        }
    }

    public function get_auto_linked_folder_name_for_user()
    {
        $shortcode = $this->get_processor()->get_shortcode();
        if (!isset($shortcode['user_upload_folders']) || 'auto' !== $shortcode['user_upload_folders']) {
            return false;
        }

        if (!empty($this->_user_folder_name)) {
            return $this->_user_folder_name;
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $userfoldername = $this->get_user_name_template($current_user);
        } else {
            $userfoldername = $this->get_guest_user_name();
        }

        $this->_user_folder_name = $userfoldername;

        return $userfoldername;
    }

    public function get_auto_linked_folder_for_user()
    {
        $shortcode = $this->get_processor()->get_shortcode();
        if (!isset($shortcode['user_upload_folders']) || 'auto' !== $shortcode['user_upload_folders']) {
            return false;
        }

        if (!empty($this->_user_folder_entry)) {
            return $this->_user_folder_entry;
        }

        // Add folder if needed
        $result = $this->create_user_folder($this->get_auto_linked_folder_name_for_user(), $this->get_processor()->get_shortcode(), 0);

        do_action('useyourdrive_after_private_folder_added', $result, $this->_processor);

        if (false === $result) {
            error_log('[WP Cloud Plugin message]: '.'Cannot find auto folder link for user');

            exit();
        }

        $this->_user_folder_entry = $result;

        return $this->_user_folder_entry;
    }

    public function get_manually_linked_folder_for_user()
    {
        $shortcode = $this->get_processor()->get_shortcode();
        if (!isset($shortcode['user_upload_folders']) || 'manual' !== $shortcode['user_upload_folders']) {
            return false;
        }

        if (!empty($this->_user_folder_entry)) {
            return $this->_user_folder_entry;
        }

        $userfolder = get_user_option('use_your_drive_linkedto');
        if (is_array($userfolder) && isset($userfolder['foldertext'])) {
            if (false === isset($userfolder['accountid'])) {
                $linked_account = $this->get_processor()->get_accounts()->get_primary_account();
            } else {
                $linked_account = $this->get_processor()->get_accounts()->get_account_by_id($userfolder['accountid']);
            }

            $this->get_processor()->set_current_account($linked_account);

            $this->_user_folder_entry = $this->get_client()->get_entry($userfolder['folderid'], false);
        } else {
            $defaultuserfolder = get_site_option('use_your_drive_guestlinkedto');
            if (is_array($defaultuserfolder) && isset($defaultuserfolder['folderid'])) {
                if (false === isset($defaultuserfolder['accountid'])) {
                    $linked_account = $this->get_processor()->get_accounts()->get_primary_account();
                } else {
                    $linked_account = $this->get_processor()->get_accounts()->get_account_by_id($defaultuserfolder['accountid']);
                }

                $this->get_processor()->set_current_account($linked_account);
                $this->_user_folder_entry = $this->get_client()->get_entry($defaultuserfolder['folderid'], false);
            } else {
                if (is_user_logged_in()) {
                    $current_user = wp_get_current_user();
                    error_log('[WP Cloud Plugin message]: '.sprintf('Cannot find manual folder link for user: %s', $current_user->user_login));
                } else {
                    error_log('[WP Cloud Plugin message]: '.'Cannot find manual folder link for guest user');
                }

                exit(-1);
            }
        }

        return $this->_user_folder_entry;
    }

    public function manually_link_folder($user_id, $linkedto)
    {
        if ('GUEST' === $user_id) {
            $result = update_site_option('use_your_drive_guestlinkedto', $linkedto);
        } else {
            $result = update_user_option($user_id, 'use_your_drive_linkedto', $linkedto, false);
        }

        if (false !== $result) {
            exit('1');
        }
    }

    public function manually_unlink_folder($user_id)
    {
        if ('GUEST' === $user_id) {
            $result = delete_site_option('use_your_drive_guestlinkedto');
        } else {
            $result = delete_user_option($user_id, 'use_your_drive_linkedto', false);
        }

        if (false !== $result) {
            exit('1');
        }
    }

    public function create_user_folder($userfoldername, $shortcode, $mswaitaftercreation = 0)
    {
        @set_time_limit(60);

        $parent_folder = $this->get_client()->get_folder($shortcode['root'], false);

        // If root folder doesn't exists
        if (empty($parent_folder) || '0' === $parent_folder['folder']->get_id()) {
            return false;
        }

        // First try to find the User Folder in Cache
        $userfolder = $this->get_client()->get_cache()->get_node_by_name($userfoldername, $parent_folder['folder']);

        /* If User Folder isn't in cache yet,
         * Update the parent folder to make sure the latest version is loaded */
        if (false === $userfolder) {
            $this->get_client()->get_cache()->pull_for_changes([$parent_folder['folder']->get_id()], true, -1);
            $userfolder = $this->get_client()->get_cache()->get_node_by_name($userfoldername, $parent_folder['folder']);
        }

        // If User Folder still isn't found, create new folder in the Cloud
        if (false === $userfolder) {
            $newfolder = new \UYDGoogle_Service_Drive_DriveFile();
            $newfolder->setName($userfoldername);
            $newfolder->setMimeType('application/vnd.google-apps.folder');
            $newfolder->setParents([$parent_folder['folder']->get_id()]);

            try {
                $api_entry = $this->get_app()->get_drive()->files->create($newfolder, ['fields' => $this->get_client()->apifilefields, 'supportsAllDrives' => true, 'userIp' => $this->get_processor()->get_user_ip(), 'enforceSingleParent' => true]);

                // Wait a moment in case many folders are created at once
                usleep($mswaitaftercreation);
            } catch (\Exception $ex) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Failed to add user folder: %s', $ex->getMessage()));

                return new \WP_Error('broke', __('Failed to add user folder', 'wpcloudplugins'));
            }

            // Add new file to our Cache
            $newentry = new Entry($api_entry);
            $userfolder = $this->get_client()->get_cache()->add_to_cache($newentry);
            $this->get_client()->get_cache()->update_cache();

            do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $userfolder);
        }

        // Check if Template folder should be created
        // 1: Is there a template folder set?
        if (empty($shortcode['user_template_dir'])) {
            return $userfolder;
        }

        // Make sure that the folder is completely loaded before we proceed, perhaps the folder already existed and contains the template folders
        $user_folder_node = $this->get_client()->get_folder($userfolder->get_id(), false);
        $userfolder = $user_folder_node['folder'];

        // 2: Has the User Folder already sub folders?
        if ($userfolder->has_children()) {
            return $userfolder;
        }

        // 3: Get the Template folder
        $cached_template_folder = $this->get_client()->get_folder($shortcode['user_template_dir'], false);

        // 4: Make sure that the Template folder can be used
        if (false === $cached_template_folder || false === $cached_template_folder['folder'] || false === $cached_template_folder['folder']->has_children()) {
            return $userfolder;
        }

        if ($userfolder->is_in_folder($cached_template_folder['folder']->get_id())) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Failed to add user folder: %s', __('User folder is inside Template folder. Please select another template folder.')));

            return new \WP_Error('broke', __('User folder is inside Template folder. Please select another template folder.', 'wpcloudplugins'));
        }

        // Copy the contents of the Template Folder into the User Folder
        $this->get_client()->copy_folder_recursive($cached_template_folder['folder'], $userfolder);

        return $userfolder;
    }

    public function create_user_folders_for_shortcodes($user_id)
    {
        $new_user = get_user_by('id', $user_id);
        $new_userfoldersname = $this->get_user_name_template($new_user);

        $useyourdrivelists = $this->get_processor()->get_shortcodes()->get_all_shortcodes();
        $current_account = $this->get_processor()->get_current_account();

        foreach ($useyourdrivelists as $list) {
            if (!isset($list['user_upload_folders']) || 'auto' !== $list['user_upload_folders']) {
                continue;
            }

            if (!isset($list['account']) || $current_account->get_id() !== $list['account']) {
                continue; // Skip shortcodes that don't belong to the account that is being processed
            }

            if (false === Helpers::check_user_role($list['view_role'], $new_user)) {
                continue; // Skip shortcodes that aren't accessible for user
            }

            $result = $this->create_user_folder($new_userfoldersname, $list);

            do_action('useyourdrive_after_private_folder_added', $result, $this->_processor);
        }
    }

    public function create_user_folders($users = [])
    {
        if (0 === count($users)) {
            return;
        }

        foreach ($users as $user) {
            $userfoldersname = $this->get_user_name_template($user);

            $result = $this->create_user_folder($userfoldersname, $this->get_processor()->get_shortcode());

            do_action('useyourdrive_after_private_folder_added', $result, $this->_processor);
        }
    }

    public function remove_user_folder($user_id)
    {
        $deleted_user = get_user_by('id', $user_id);
        $userfoldername = $this->get_user_name_template($deleted_user);

        $useyourdrivelists = $this->get_processor()->get_shortcodes()->get_all_shortcodes();
        $current_account = $this->get_processor()->get_current_account();

        // Apply Batch
        $do_delete = false;

        $batch = new \UYDGoogle_Http_Batch($this->get_client()->get_library());

        foreach ($useyourdrivelists as $list) {
            if (!isset($list['user_upload_folders']) || 'auto' !== $list['user_upload_folders']) {
                continue;
            }

            if (!isset($list['account']) || $current_account->get_id() !== $list['account']) {
                continue; // Skip shortcodes that don't belong to the account that is being processed
            }

            if (false === Helpers::check_user_role($list['view_role'], $deleted_user)) {
                continue; // Skip shortcodes that aren't accessible for user
            }

            $params = [
                'q' => "'".$list['root']."' in parents and name='".$userfoldername."' and mimeType='application/vnd.google-apps.folder' and trashed = false",
                'userIp' => $this->get_processor()->get_user_ip(),
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            try {
                $this->get_client()->get_library()->setUseBatch(false);
                $api_list = $this->get_app()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Failed to remove user folder: %s', $ex->getMessage()));

                return false;
            }

            $api_files = $api_list->getFiles();

            // Stop when no User Folders are found
            if (0 === count($api_files)) {
                continue;
            }

            $do_delete = true;
            // Delete all the user folders that are found
            // 1: Create an the entry for Patch
            $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
            $updateentry->setTrashed(true);

            $this->get_client()->get_library()->setUseBatch(true);
            foreach ($api_files as $api_file) {
                $batch->add($this->get_app()->get_drive()->files->update($api_file->getId(), $updateentry, ['supportsAllDrives' => true, 'userIp' => $this->get_processor()->get_user_ip(), 'enforceSingleParent' => true]));
            }
        }

        if ($do_delete) {
            try {
                $batch_result = $batch->execute();
            } catch (\Exception $ex) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Failed to remove user folder: %s', $ex->getMessage()));
            }
        }

        $this->get_client()->get_library()->setUseBatch(false);

        $this->get_client()->get_cache()->pull_for_changes([$list['root']], true);

        return true;
    }

    public function update_user_folder($user_id, $old_user)
    {
        $updated_user = get_user_by('id', $user_id);
        $new_userfoldersname = $this->get_user_name_template($updated_user);

        $old_userfoldersname = $this->get_user_name_template($old_user);

        if ($new_userfoldersname === $old_userfoldersname) {
            return false;
        }

        $useyourdrivelists = $this->get_processor()->get_shortcodes()->get_all_shortcodes();
        $current_account = $this->get_processor()->get_current_account();

        foreach ($useyourdrivelists as $listtoken => $list) {
            if (!isset($list['user_upload_folders']) || 'auto' !== $list['user_upload_folders']) {
                continue;
            }

            if (!isset($list['account']) || $current_account->get_id() !== $list['account']) {
                continue; // Skip shortcodes that don't belong to the account that is being processed
            }

            if (false === Helpers::check_user_role($list['view_role'], $updated_user)) {
                continue; // Skip shortcodes that aren't accessible for user
            }

            if (defined('use_your_drive_update_user_folder_'.$list['root'].'_'.$new_userfoldersname)) {
                continue;
            }

            define('use_your_drive_update_user_folder_'.$list['root'].'_'.$new_userfoldersname, true);

            $params = [
                'q' => "'".$list['root']."' in parents and name='".$old_userfoldersname."' and mimeType='application/vnd.google-apps.folder' and trashed = false",
                'userIp' => $this->get_processor()->get_user_ip(),
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            try {
                $api_list = $this->get_app()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                error_log('[WP Cloud Plugin message]: '.sprintf('Failed to update user folder: %s', $ex->getMessage()));

                return false;
            }

            $api_files = $api_list->getFiles();

            // Stop when no User Folders are found
            if (0 === count($api_files)) {
                continue;
            }

            // Delete all the user folders that are found
            // 1: Create an the entry for Patch
            $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
            $updateentry->setName($new_userfoldersname);

            foreach ($api_files as $api_file) {
                try {
                    $this->get_client()->update_entry($api_file->getId(), $updateentry);
                } catch (\Exception $ex) {
                    error_log('[WP Cloud Plugin message]: '.sprintf('Failed to update user folder: %s', $ex->getMessage()));

                    continue;
                }
            }
        }

        $this->get_client()->get_cache()->pull_for_changes([$list['root']], true);

        return true;
    }

    public function get_user_name_template($user_data)
    {
        $user_folder_name = strtr($this->_user_name_template, [
            '%user_login%' => isset($user_data->user_login) ? $user_data->user_login : '',
            '%user_email%' => isset($user_data->user_email) ? $user_data->user_email : '',
            '%user_firstname%' => isset($user_data->user_firstname) ? $user_data->user_firstname : '',
            '%user_lastname%' => isset($user_data->user_lastname) ? $user_data->user_lastname : '',
            '%display_name%' => isset($user_data->display_name) ? $user_data->display_name : '',
            '%ID%' => isset($user_data->ID) ? $user_data->ID : '',
            '%user_role%' => isset($user_data->roles) ? implode(',', $user_data->roles) : '',
            '%jjjj-mm-dd%' => date('Y-m-d'),
            '%ip%' => $this->get_processor()->userip,
        ]);

        if (false !== strpos($user_folder_name, '%location%')) {
            $ip = $this->get_processor()->userip;
            // Geo location if required

            $user_folder_name = strtr($user_folder_name, [
                '%location%' => Helpers::get_user_location($ip),
            ]);
        }

        return apply_filters('useyourdrive_private_folder_name', $user_folder_name, $this->get_processor());
    }

    public function get_guest_user_name()
    {
        $username = $this->get_guest_id();

        $current_user = new \stdClass();
        $current_user->user_login = md5($username);
        $current_user->display_name = $username;
        $current_user->ID = $username;
        $current_user->user_role = __('Anonymous user', 'wpcloudplugins');

        $user_folder_name = $this->get_user_name_template($current_user);

        return apply_filters('useyourdrive_private_folder_name_guests', __('Guests', 'wpcloudplugins').' - '.$user_folder_name, $this->get_processor());
    }

    public function get_guest_id()
    {
        $id = uniqid();
        if (!isset($_COOKIE['UYD-ID'])) {
            $expire = time() + 60 * 60 * 24 * 7;

            Helpers::set_cookie('UYD-ID', $id, $expire, COOKIEPATH, COOKIE_DOMAIN, false, false, 'strict');
        } else {
            $id = $_COOKIE['UYD-ID'];
        }

        return $id;
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
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
}
