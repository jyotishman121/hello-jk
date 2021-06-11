<?php

namespace TheLion\UseyourDrive;

class Processor
{
    public $options = [];
    public $userip;
    public $mobile = false;
    protected $listtoken = '';
    protected $_rootFolder;
    protected $_lastFolder;
    protected $_folderPath;
    protected $_requestedEntry;
    protected $_load_scripts = ['general' => false, 'files' => false, 'upload' => false, 'mediaplayer' => false];

    /**
     * @var \TheLion\UseyourDrive\Main
     */
    private $_main;

    /**
     * @var \TheLion\UseyourDrive\App
     */
    private $_app;

    /**
     * @var \TheLion\UseyourDrive\Client
     */
    private $_client;

    /**
     * @var \TheLion\UseyourDrive\User
     */
    private $_user;

    /**
     * @var \TheLion\UseyourDrive\UserFolders
     */
    private $_userfolders;

    /**
     * @var \TheLion\UseyourDrive\Cache
     */
    private $_cache;

    /**
     * @var \TheLion\UseyourDrive\Shortcodes
     */
    private $_shortcodes;

    /**
     * @var \TheLion\UseyourDrive\Account
     */
    private $_current_account;

    /**
     * Construct the plugin object.
     */
    public function __construct(Main $_main)
    {
        $this->_main = $_main;
        register_shutdown_function([&$this, 'do_shutdown']);

        $this->settings = get_option('use_your_drive_settings');

        if ($this->is_network_authorized()) {
            $this->settings = array_merge($this->settings, get_site_option('useyourdrive_network_settings', []));
        }

        $this->userip = Helpers::get_user_ip();

        if (isset($_REQUEST['mobile']) && ('true' === $_REQUEST['mobile'])) {
            $this->mobile = true;
        }

        // If the user wants a hard refresh, set this globally
        if (isset($_REQUEST['hardrefresh']) && 'true' === $_REQUEST['hardrefresh'] && (!defined('FORCE_REFRESH'))) {
            define('FORCE_REFRESH', true);
        }
    }

    public function start_process()
    {
        if (!isset($_REQUEST['action'])) {
            error_log('[WP Cloud Plugin message]: '." Function start_process() requires an 'action' request");

            exit();
        }

        if (isset($_REQUEST['account_id'])) {
            $requested_account = $this->get_accounts()->get_account_by_id($_REQUEST['account_id']);
            if (null !== $requested_account) {
                $this->set_current_account($requested_account);
            } else {
                error_log(sprintf('[WP Cloud Plugin message]: '." Function start_process() cannot use the requested account (ID: %s) as it isn't linked with the plugin", $_REQUEST['account_id']));

                exit();
            }
        }

        do_action('useyourdrive_before_start_process', $_REQUEST['action'], $this);

        $authorized = $this->_is_action_authorized();

        if ((true === $authorized) && ('useyourdrive-revoke' === $_REQUEST['action'])) {
            if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
                if (null === $this->get_current_account()) {
                    exit(-1);
                }

                if ('true' === $_REQUEST['force']) {
                    $this->get_accounts()->remove_account($this->get_current_account()->get_id());
                } else {
                    $this->get_app()->revoke_token($this->get_current_account());
                }
            }

            exit(1);
        }

        if ('useyourdrive-factory-reset' === $_REQUEST['action']) {
            if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
                $this->get_main()->do_factory_reset();
            }

            exit(1);
        }

        if ('useyourdrive-reset-cache' === $_REQUEST['action']) {
            if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
                $this->reset_complete_cache(true);
            }

            exit(1);
        }

        if ('useyourdrive-reset-statistics' === $_REQUEST['action']) {
            if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
                Events::truncate_database();
            }

            exit(1);
        }

        if (is_wp_error($authorized)) {
            error_log('[WP Cloud Plugin message]: '." Function start_process() isn't authorized");

            if ('1' === $this->options['debug']) {
                exit($authorized->get_error_message());
            }

            exit();
        }

        if ((!isset($_REQUEST['listtoken']))) {
            error_log('[WP Cloud Plugin message]: '." Function start_process() requires a 'listtoken' request");
            error_log(var_export($_REQUEST, true));

            exit();
        }

        $this->listtoken = $_REQUEST['listtoken'];
        $this->options = $this->get_shortcodes()->get_shortcode_by_id($this->listtoken);

        if (false === $this->options) {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            error_log('[WP Cloud Plugin message]: '.' Function start_process('.$_REQUEST['action'].") hasn't received a valid listtoken (".$this->listtoken.") on: {$url} \n");

            exit();
        }

        if (false === $this->get_user()->can_view()) {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $request = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            error_log('[WP Cloud Plugin message]: '." Function start_process() discovered that an user didn't have the permission to view the plugin on {$url} requested via {$request}");

            exit();
        }

        if (null === $this->get_current_account() || false === $this->get_current_account()->get_authorization()->has_access_token()) {
            error_log('[WP Cloud Plugin message]: '." Function _is_action_authorized() discovered that the plugin doesn't have an access token");

            return new \WP_Error('broke', '<strong>'.sprintf(__('%s needs your help!', 'wpcloudplugins'), 'Use-your-Drive').'</strong> '.__('Authorize the plugin!', 'wpcloudplugins').'.');
        }

        $this->get_client();

        // Refresh Cache if needed
        //$this->get_cache()->reset_cache();

        // Set rootFolder
        if ('manual' === $this->options['user_upload_folders']) {
            $userfolder = $this->get_user_folders()->get_manually_linked_folder_for_user();
            if (is_wp_error($userfolder) || false === $userfolder) {
                error_log('[WP Cloud Plugin message]: '.'Cannot find a manually linked folder for user');

                exit('-1');
            }
            $this->_rootFolder = $userfolder->get_id();
        } elseif (('auto' === $this->options['user_upload_folders']) && !Helpers::check_user_role($this->options['view_user_folders_role'])) {
            $userfolder = $this->get_user_folders()->get_auto_linked_folder_for_user();

            if (is_wp_error($userfolder) || false === $userfolder) {
                error_log('[WP Cloud Plugin message]: '.'Cannot find a auto linked folder for user');

                exit('-1');
            }
            $this->_rootFolder = $userfolder->get_id();
        } else {
            $this->_rootFolder = $this->options['root'];
        }

        $this->_lastFolder = $this->_rootFolder;
        if (isset($_REQUEST['lastFolder']) && '' !== $_REQUEST['lastFolder']) {
            $this->_lastFolder = $_REQUEST['lastFolder'];
        }

        $this->_requestedEntry = $this->_lastFolder;
        if (isset($_REQUEST['id']) && '' !== $_REQUEST['id']) {
            $this->_requestedEntry = $_REQUEST['id'];
        }

        // Remove all cache files for current shortcode when refreshing, otherwise check for new changes
        if (defined('FORCE_REFRESH')) {
            CacheRequest::clear_request_cache();
            $this->get_cache()->pull_for_changes([$this->_lastFolder]);
        } else {
            // Pull for changes if needed
            if ('No' === $this->get_setting('cache_update_via_wpcron')) {
                $this->get_cache()->pull_for_changes([$this->_lastFolder]);
            }
        }

        if (!empty($_REQUEST['folderPath'])) {
            $this->_folderPath = json_decode(base64_decode($_REQUEST['folderPath']), true);

            if (false === $this->_folderPath || null === $this->_folderPath || !is_array($this->_folderPath)) {
                // Build path when starting somewhere in the folder
                $current_entry = $this->get_client()->get_entry($this->get_requested_entry());

                if (!empty($current_entry)) {
                    $parents = $current_entry->get_all_parent_folders();
                    $folder_path = [];

                    foreach ($parents as $parent_id => $parent) {
                        $is_in_root = $parent->is_in_folder($this->_rootFolder);

                        if (false === $is_in_root) {
                            break;
                        }

                        $folder_path[] = $parent_id;
                    }

                    $this->_folderPath = array_reverse($folder_path);
                } else {
                    $this->_folderPath = [$this->_rootFolder];
                }
            }

            $key = array_search($this->_requestedEntry, $this->_folderPath);
            if (false !== $key) {
                array_splice($this->_folderPath, $key);
                if (0 === count($this->_folderPath)) {
                    $this->_folderPath = [$this->_rootFolder];
                }
            }
        } else {
            $this->_folderPath = [$this->_rootFolder];
        }

        // Check if the request is cached
        if (in_array($_REQUEST['action'], ['useyourdrive-get-filelist', 'useyourdrive-get-gallery', 'useyourdrive-get-playlist', 'useyourdrive-thumbnail'])) {
            // And Set GZIP compression if possible
            $this->_set_gzip_compression();

            if (!defined('FORCE_REFRESH')) {
                $cached_request = new CacheRequest($this);
                if ($cached_request->is_cached()) {
                    echo $cached_request->get_cached_response();

                    exit();
                }
            }
        }

        do_action('useyourdrive_start_process', $_REQUEST['action'], $this);

        switch ($_REQUEST['action']) {
            case 'useyourdrive-get-filelist':
                $filebrowser = new Filebrowser($this);

                if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && '1' === $this->options['search']) { // Search files
                    $filelist = $filebrowser->search_files();
                } else {
                    $filelist = $filebrowser->get_files_list(); // Read folder
                }

                break;

            case 'useyourdrive-download':
                if (false === $this->get_user()->can_download()) {
                    exit();
                }

                $file = $this->get_client()->download_entry();

                break;

            case 'useyourdrive-preview':
                $file = $this->get_client()->preview_entry();

                break;

            case 'useyourdrive-edit':
                if (false === $this->get_user()->can_edit()) {
                    exit();
                }

                $file = $this->get_client()->edit_entry();

                break;

            case 'useyourdrive-thumbnail':
                if (isset($_REQUEST['type']) && 'folder-thumbnails' === $_REQUEST['type']) {
                    $thumbnails = $this->get_client()->get_folder_thumbnails();
                    $response = json_encode($thumbnails);

                    $cached_request = new CacheRequest($this);
                    $cached_request->add_cached_response($response);

                    echo $response;
                } else {
                    $file = $this->get_client()->build_thumbnail();
                }

                break;

            case 'useyourdrive-create-zip':
                if (false === $this->get_user()->can_download()) {
                    exit();
                }

                $request_id = $_REQUEST['request_id'];

                switch ($_REQUEST['type']) {
                    case 'do-zip':
                         $zip = new Zip($this, $request_id);
                        $zip->do_zip();

                        break;

                    case 'get-progress':
                        Zip::get_status($request_id);

                        break;
                }

                break;

            case 'useyourdrive-embedded':
                $links = $this->get_client()->create_links(false);
                echo json_encode($links);

                break;

            case 'useyourdrive-create-link':
                if (isset($_REQUEST['entries'])) {
                    $links = $this->get_client()->create_links();
                    echo json_encode($links);
                } else {
                    $link = $this->get_client()->create_link();
                    echo json_encode($link);
                }

                break;

            case 'useyourdrive-get-gallery':
                if (is_wp_error($authorized)) {
                    // No valid token is set
                    echo json_encode(['lastpath' => base64_encode(json_encode($this->_lastFolder)), 'folder' => '', 'html' => '']);

                    exit();
                }

                $gallery = new Gallery($this);

                if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && '1' === $this->options['search']) { // Search files
                    $imagelist = $gallery->search_image_files();
                } else {
                    $imagelist = $gallery->get_images_list(); // Read folder
                }

                break;

            case 'useyourdrive-upload-file':
                $user_can_upload = $this->get_user()->can_upload();

                if (is_wp_error($authorized) || false === $user_can_upload) {
                    exit();
                }

                $upload_processor = new Upload($this);

                switch ($_REQUEST['type']) {
                    case 'upload-preprocess':
                        $status = $upload_processor->upload_pre_process();

                        break;

                    case 'do-upload':
                        $upload = $upload_processor->do_upload();

                        break;

                    case 'get-status':
                        $status = $upload_processor->get_upload_status();

                        break;

                    case 'get-direct-url':
                        $status = $upload_processor->do_upload_direct();

                        break;

                    case 'upload-convert':
                        $status = $upload_processor->upload_convert();

                        break;

                    case 'upload-postprocess':
                        $status = $upload_processor->upload_post_process();

                        break;
                }

                exit();

                break;

            case 'useyourdrive-delete-entries':
//Check if user is allowed to delete entry
                $user_can_delete = $this->get_user()->can_delete_files() || $this->get_user()->can_delete_folders();

                if (is_wp_error($authorized) || false === $user_can_delete) {
                    echo json_encode(['result' => '-1', 'msg' => __('Failed to delete entry', 'wpcloudplugins')]);

                    exit();
                }

                $entries_to_delete = [];
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $entries_to_delete[] = $requested_id;
                }

                $entries = $this->get_client()->delete_entries($entries_to_delete);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry)) {
                        echo json_encode(['result' => '-1', 'msg' => __('Not all entries could be deleted', 'wpcloudplugins')]);

                        exit();
                    }
                }
                echo json_encode(['result' => '1', 'msg' => __('Entry was deleted', 'wpcloudplugins')]);

                exit();

                break;

            case 'useyourdrive-rename-entry':
//Check if user is allowed to rename entry
                $user_can_rename = $this->get_user()->can_rename_files() || $this->get_user()->can_rename_folders();

                if (false === $user_can_rename) {
                    echo json_encode(['result' => '-1', 'msg' => __('Failed to rename entry', 'wpcloudplugins')]);

                    exit();
                }

//Strip unsafe characters
                $newname = rawurldecode($_REQUEST['newname']);
                $new_filename = Helpers::filter_filename($newname, false);

                $file = $this->get_client()->rename_entry($new_filename);

                if (is_wp_error($file)) {
                    echo json_encode(['result' => '-1', 'msg' => $file->get_error_message()]);
                } else {
                    echo json_encode(['result' => '1', 'msg' => __('Entry was renamed', 'wpcloudplugins')]);
                }

                exit();

                break;

            case 'useyourdrive-copy-entry':
                //Check if user is allowed to rename entry
                $user_can_copy = $this->get_user()->can_copy_files() || $this->get_user()->can_copy_folders();

                if (false === $user_can_copy) {
                    echo json_encode(['result' => '-1', 'msg' => __('Failed to copy entry', 'wpcloudplugins')]);

                    exit();
                }

                //Strip unsafe characters
                $newname = rawurldecode($_REQUEST['newname']);
                $new_filename = Helpers::filter_filename($newname, false);

                $file = $this->get_client()->copy_entry(null, null, $new_filename);

                if (is_wp_error($file)) {
                    echo json_encode(['result' => '-1', 'msg' => $file->get_error_message()]);
                } else {
                    echo json_encode(['result' => '1', 'msg' => __('Entry was copied', 'wpcloudplugins')]);
                }

                exit();

                break;

            case 'useyourdrive-move-entries':
                // Check if user is allowed to move entry
                $user_can_move = $this->get_user()->can_move_files() || $this->get_user()->can_move_folders();

                if (false === $user_can_move) {
                    echo json_encode(['result' => '-1', 'msg' => __('Failed to move', 'wpcloudplugins')]);

                    exit();
                }

                $entries_to_move = [];
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $entries_to_move[] = $requested_id;
                }

                $entries = $this->get_client()->move_entries($entries_to_move, $_REQUEST['target']);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry) || empty($entry)) {
                        echo json_encode(['result' => '-1', 'msg' => __('Not all entries could be moved', 'wpcloudplugins')]);

                        exit();
                    }
                }
                echo json_encode(['result' => '1', 'msg' => __('Successfully moved to new location', 'wpcloudplugins')]);

                exit();

                break;

            case 'useyourdrive-edit-description-entry':
                //Check if user is allowed to rename entry
                $user_can_editdescription = $this->get_user()->can_edit_description();

                if (false === $user_can_editdescription) {
                    echo json_encode(['result' => '-1', 'msg' => __('Failed to edit description', 'wpcloudplugins')]);

                    exit();
                }

                $newdescription = sanitize_textarea_field(wp_unslash($_REQUEST['newdescription']));
                $result = $this->get_client()->update_description($newdescription);

                if (is_wp_error($result)) {
                    echo json_encode(['result' => '-1', 'msg' => $result->get_error_message()]);
                } else {
                    echo json_encode(['result' => '1', 'msg' => __('Description was edited', 'wpcloudplugins'), 'description' => $result]);
                }

                exit();

                break;

            case 'useyourdrive-create-entry':
                //Strip unsafe characters
                $_name = rawurldecode($_REQUEST['name']);
                $new_name = Helpers::filter_filename($_name, false);
                $mimetype = $_REQUEST['mimetype'];

                //Check if user is allowed
                $user_can_create_entry = ('application/vnd.google-apps.folder' === $mimetype) ? $this->get_user()->can_add_folders() : $this->get_user()->can_create_document();

                if (false === $user_can_create_entry) {
                    echo json_encode(['result' => '-1', 'msg' => __('Failed to add entry', 'wpcloudplugins')]);

                    exit();
                }

                $file = $this->get_client()->add_entry($new_name, $mimetype);
                $new_folder_id = ('application/vnd.google-apps.folder' === $mimetype) ? $file->get_id() : $this->get_last_folder();

                if (is_wp_error($file)) {
                    echo json_encode(['result' => '-1', 'msg' => $file->get_error_message()]);
                } else {
                    echo json_encode(['result' => '1', 'msg' => $new_name.' '.__('was added', 'wpcloudplugins'), 'lastFolder' => $new_folder_id]);
                }

                exit();

                break;

            case 'useyourdrive-get-playlist':
                $mediaplayer = new Mediaplayer($this);
                $playlist = $mediaplayer->get_media_list();

                break;

            case 'useyourdrive-stream':
                $file = $this->get_client()->stream_entry();

                break;

            case 'useyourdrive-event-log':
                return;

            default:
                error_log('[WP Cloud Plugin message]: '.sprintf('No valid AJAX request: %s', $_REQUEST['action']));

                exit('Use-your-Drive: '.__('No valid AJAX request', 'wpcloudplugins'));
        }

        exit();
    }

    public function create_from_shortcode($atts)
    {
        $atts = (is_string($atts)) ? [] : $atts;
        $atts = $this->remove_deprecated_options($atts);

        $defaults = [
            'singleaccount' => '1',
            'account' => false,
            'startaccount' => false,
            'dir' => false,
            'class' => '',
            'startid' => false,
            'mode' => 'files',
            'userfolders' => '0',
            'usertemplatedir' => '',
            'viewuserfoldersrole' => 'administrator',
            'userfoldernametemplate' => '',
            'showfiles' => '1',
            'maxfiles' => '-1',
            'showfolders' => '1',
            'filesize' => '1',
            'filedate' => '1',
            'filelayout' => 'grid',
            'showext' => '1',
            'sortfield' => 'name',
            'sortorder' => 'asc',
            'showbreadcrumb' => '1',
            'candownloadzip' => '0',
            'canpopout' => '0',
            'lightboxnavigation' => '1',
            'showsharelink' => '0',
            'showrefreshbutton' => '1',
            'roottext' => __('Start', 'wpcloudplugins'),
            'search' => '1',
            'searchcontents' => '0',
            'searchfrom' => 'parent',
            'searchterm' => '',
            'include' => '*',
            'includeext' => '*',
            'exclude' => '*',
            'excludeext' => '*',
            'maxwidth' => '100%',
            'maxheight' => '',
            'viewrole' => 'administrator|editor|author|contributor|subscriber|pending|guest',
            'downloadrole' => 'administrator|editor|author|contributor|subscriber|pending|guest',
            'sharerole' => 'all',
            'edit' => '0',
            'editrole' => 'administrator|editor|author',
            'previewinline' => '1',
            'forcedownload' => '0',
            'maximages' => '25',
            'quality' => '90',
            'slideshow' => '0',
            'pausetime' => '5000',
            'showfilenames' => '0',
            'showdescriptionsontop' => '0',
            'targetheight' => '300',
            'mediaskin' => '',
            'mediabuttons' => 'prevtrack|playpause|nexttrack|volume|current|duration|fullscreen',
            'autoplay' => '0',
            'hideplaylist' => '0',
            'showplaylistonstart' => '1',
            'playlistinline' => '0',
            'playlistthumbnails' => '1',
            'linktomedia' => '0',
            'linktoshop' => '',
            'ads' => '0',
            'ads_tag_url' => '',
            'ads_skipable' => '1',
            'ads_skipable_after' => '',
            'notificationupload' => '0',
            'notificationdownload' => '0',
            'notificationdeletion' => '0',
            'notificationemail' => '%admin_email%',
            'notification_skipemailcurrentuser' => '0',
            'upload' => '0',
            'upload_folder' => '1',
            'upload_auto_start' => '1',
            'uploadext' => '.',
            'uploadrole' => 'administrator|editor|author|contributor|subscriber',
            'upload_encryption' => '0',
            'upload_encryption_passphrase' => '',
            'minfilesize' => '0',
            'maxfilesize' => '0',
            'maxnumberofuploads' => '-1',
            'convert' => '0',
            'convertformats' => 'all',
            'overwrite' => '0',
            'delete' => '0',
            'deletefilesrole' => 'administrator|editor',
            'deletefoldersrole' => 'administrator|editor',
            'deletetotrash' => '1',
            'rename' => '0',
            'renamefilesrole' => 'administrator|editor',
            'renamefoldersrole' => 'administrator|editor',
            'move' => '0',
            'movefilesrole' => 'administrator|editor',
            'movefoldersrole' => 'administrator|editor',
            'copy' => '0',
            'copyfilesrole' => 'administrator|editor',
            'copyfoldersrole' => 'administrator|editor',
            'editdescription' => '0',
            'editdescriptionrole' => 'administrator|editor',
            'addfolder' => '0',
            'addfolderrole' => 'administrator|editor',
            'createdocument' => '0',
            'createdocumentrole' => 'administrator|editor',
            'deeplink' => '0',
            'deeplinkrole' => 'all',
            'mcepopup' => '0',
            'debug' => '0',
            'demo' => '0',
        ];

        //Create a unique identifier
        $this->listtoken = md5(serialize($defaults).serialize($atts));

        //Read shortcode
        extract(shortcode_atts($defaults, $atts));

        $cached_shortcode = $this->get_shortcodes()->get_shortcode_by_id($this->listtoken);

        if (false === $cached_shortcode) {
            switch ($mode) {
                case 'gallery':
                    $includeext = ('*' == $includeext) ? 'gif|jpg|jpeg|png|bmp|cr2|crw|raw|tif|tiff' : $includeext;
                    $uploadext = ('.' == $uploadext) ? 'gif|jpg|jpeg|png|bmp|cr2|crw|raw|tif|tiff' : $uploadext;
                    // no break
                case 'search':
                    $searchfrom = 'root';
                    // no break
                default:
                    break;
            }

            if (!empty($account)) {
                $singleaccount = '1';
            }

            if ('0' === $singleaccount) {
                $dir = 'drive';
                $account = false;
            }

            if (empty($account)) {
                $primary_account = $this->get_accounts()->get_primary_account();
                if (null !== $primary_account) {
                    $account = $primary_account->get_id();
                }
            }

            $account_class = $this->get_accounts()->get_account_by_id($account);
            if (null === $account_class) {
                error_log('[WP Cloud Plugin message]: shortcode cannot be rendered as the requested account is not linked with the plugin');

                return '<i>>>> '.__('ERROR: Contact the Administrator to see this content', 'wpcloudplugins').' <<<</i>';
            }

            $this->set_current_account($account_class);

            $rootfolder = $this->get_client()->get_root_folder();
            if (is_wp_error($rootfolder)) {
                if ('1' === $debug) {
                    return "<div id='message' class='error'><p>".$rootfolder->get_error_message().'</p></div>';
                }

                return false;
            }
            if (empty($rootfolder)) {
                if ('1' === $debug) {
                    return "<div id='message' class='error'><p>".__('Please authorize the plugin', 'wpcloudplugins').'</p></div>';
                }

                return false;
            }
            $rootfolderid = $rootfolder->get_id();

            if (empty($dir)) {
                $dir = $this->get_client()->get_my_drive()->get_id();
            }

            //Force $candownloadzip = 0 if we can't use ZipArchive
            if (!class_exists('ZipArchive')) {
                $candownloadzip = '0';
            }

            if ('1' === $upload_encryption && (version_compare(phpversion(), '7.1.0', '>'))) {
                $upload_encryption = '0';
            }

            $convertformats = explode('|', $convertformats);

            // Explode roles
            $viewrole = explode('|', $viewrole);
            $downloadrole = explode('|', $downloadrole);
            $sharerole = explode('|', $sharerole);
            $editrole = explode('|', $editrole);
            $uploadrole = explode('|', $uploadrole);
            $deletefilesrole = explode('|', $deletefilesrole);
            $deletefoldersrole = explode('|', $deletefoldersrole);
            $renamefilesrole = explode('|', $renamefilesrole);
            $renamefoldersrole = explode('|', $renamefoldersrole);
            $movefilesrole = explode('|', $movefilesrole);
            $movefoldersrole = explode('|', $movefoldersrole);
            $copyfilesrole = explode('|', $copyfilesrole);
            $copyfoldersrole = explode('|', $copyfoldersrole);
            $editdescriptionrole = explode('|', $editdescriptionrole);
            $addfolderrole = explode('|', $addfolderrole);
            $createdocumentrole = explode('|', $createdocumentrole);

            $viewuserfoldersrole = explode('|', $viewuserfoldersrole);
            $deeplinkrole = explode('|', $deeplinkrole);
            $mediabuttons = explode('|', $mediabuttons);

            $this->options = [
                'single_account' => $singleaccount,
                'account' => $account,
                'startaccount' => $startaccount,
                'root' => $dir,
                'class' => $class,
                'base' => $rootfolderid,
                'startid' => $startid,
                'mode' => $mode,
                'user_upload_folders' => $userfolders,
                'user_template_dir' => $usertemplatedir,
                'view_user_folders_role' => $viewuserfoldersrole,
                'user_folder_name_template' => $userfoldernametemplate,
                'mediaskin' => $mediaskin,
                'mediabuttons' => $mediabuttons,
                'autoplay' => $autoplay,
                'hideplaylist' => $hideplaylist,
                'showplaylistonstart' => $showplaylistonstart,
                'playlistinline' => $playlistinline,
                'playlistthumbnails' => $playlistthumbnails,
                'linktomedia' => $linktomedia,
                'linktoshop' => $linktoshop,
                'ads' => $ads,
                'ads_tag_url' => $ads_tag_url,
                'ads_skipable' => $ads_skipable,
                'ads_skipable_after' => $ads_skipable_after,
                'show_files' => $showfiles,
                'show_folders' => $showfolders,
                'show_filesize' => $filesize,
                'show_filedate' => $filedate,
                'max_files' => $maxfiles,
                'filelayout' => $filelayout,
                'show_ext' => $showext,
                'sort_field' => $sortfield,
                'sort_order' => $sortorder,
                'show_breadcrumb' => $showbreadcrumb,
                'can_download_zip' => $candownloadzip,
                'can_popout' => $canpopout,
                'lightbox_navigation' => $lightboxnavigation,
                'show_sharelink' => $showsharelink,
                'show_refreshbutton' => $showrefreshbutton,
                'root_text' => $roottext,
                'search' => $search,
                'searchcontents' => $searchcontents,
                'searchfrom' => $searchfrom,
                'searchterm' => $searchterm,
                'include' => explode('|', htmlspecialchars_decode($include)),
                'include_ext' => explode('|', strtolower($includeext)),
                'exclude' => explode('|', htmlspecialchars_decode($exclude)),
                'exclude_ext' => explode('|', strtolower($excludeext)),
                'maxwidth' => $maxwidth,
                'maxheight' => $maxheight,
                'view_role' => $viewrole,
                'download_role' => $downloadrole,
                'share_role' => $sharerole,
                'edit' => $edit,
                'edit_role' => $editrole,
                'previewinline' => $previewinline,
                'forcedownload' => $forcedownload,
                'maximages' => $maximages,
                'notificationupload' => $notificationupload,
                'notificationdownload' => $notificationdownload,
                'notificationdeletion' => $notificationdeletion,
                'notificationemail' => $notificationemail,
                'notification_skip_email_currentuser' => $notification_skipemailcurrentuser,
                'upload' => $upload,
                'upload_folder' => $upload_folder,
                'upload_auto_start' => $upload_auto_start,
                'upload_ext' => strtolower($uploadext),
                'upload_role' => $uploadrole,
                'upload_encryption' => $upload_encryption,
                'upload_encryption_passphrase' => $upload_encryption_passphrase,
                'minfilesize' => $minfilesize,
                'maxfilesize' => $maxfilesize,
                'maxnumberofuploads' => $maxnumberofuploads,
                'convert' => $convert,
                'convert_formats' => $convertformats,
                'overwrite' => $overwrite,
                'delete' => $delete,
                'delete_files_role' => $deletefilesrole,
                'delete_folders_role' => $deletefoldersrole,
                'deletetotrash' => $deletetotrash,
                'rename' => $rename,
                'rename_files_role' => $renamefilesrole,
                'rename_folders_role' => $renamefoldersrole,
                'move' => $move,
                'move_files_role' => $movefilesrole,
                'move_folders_role' => $movefoldersrole,
                'copy' => $copy,
                'copy_files_role' => $copyfilesrole,
                'copy_folders_role' => $copyfoldersrole,
                'editdescription' => $editdescription,
                'editdescription_role' => $editdescriptionrole,
                'addfolder' => $addfolder,
                'addfolder_role' => $addfolderrole,
                'create_document' => $createdocument,
                'create_document_role' => $createdocumentrole,
                'deeplink' => $deeplink,
                'deeplink_role' => $deeplinkrole,
                'quality' => $quality,
                'show_filenames' => $showfilenames,
                'show_descriptions_on_top' => $showdescriptionsontop,
                'targetheight' => $targetheight,
                'slideshow' => $slideshow,
                'pausetime' => $pausetime,
                'mcepopup' => $mcepopup,
                'debug' => $debug,
                'demo' => $demo,
                'expire' => strtotime('+1 weeks'),
                'listtoken' => $this->listtoken, ];

            $this->options = apply_filters('useyourdrive_shortcode_add_options', $this->options, $this, $atts);

            $this->save_shortcodes();

            $this->options = apply_filters('useyourdrive_shortcode_set_options', $this->options, $this, $atts);

            //Create userfolders if needed

            if (('auto' === $this->options['user_upload_folders'])) {
                if ('Yes' === $this->settings['userfolder_onfirstvisit']) {
                    $allusers = [];
                    $roles = $this->options['view_role'];

                    foreach ($roles as $role) {
                        $users_query = new \WP_User_Query([
                            'fields' => 'all_with_meta',
                            'role' => $role,
                            'orderby' => 'display_name',
                        ]);
                        $results = $users_query->get_results();
                        if ($results) {
                            $allusers = array_merge($allusers, $results);
                        }
                    }

                    $userfolder = $this->get_user_folders()->create_user_folders($allusers);
                }
            }
        } else {
            $this->options = apply_filters('useyourdrive_shortcode_set_options', $cached_shortcode, $this, $atts);
        }

        if (null === $this->get_current_account() || false === $this->get_current_account()->get_authorization()->has_access_token()) {
            return '<i>>>> '.__('ERROR: Contact the Administrator to see this content', 'wpcloudplugins').' <<<</i>';
        }

        ob_start();
        $this->render_template();

        return ob_get_clean();
    }

    public function render_template()
    {
        // Reload User Object for this new shortcode
        $user = $this->get_user('reload');

        if (false === $this->get_user()->can_view()) {
            do_action('useyourdrive_shortcode_no_view_permission', $this);

            return;
        }

        // Render the  template
        $dataid = ''; //(($this->options['user_upload_folders'] !== '0') && !Helpers::check_user_role($this->options['view_user_folders_role'])) ? '' : $this->options['root'];

        $colors = $this->get_setting('colors');

        if ('manual' === $this->options['user_upload_folders']) {
            $userfolder = get_user_option('use_your_drive_linkedto');
            if (is_array($userfolder) && isset($userfolder['folderid'])) {
                $dataid = $userfolder['folderid'];
            } else {
                $defaultuserfolder = get_site_option('use_your_drive_guestlinkedto');
                if (is_array($defaultuserfolder) && isset($defaultuserfolder['folderid'])) {
                    $dataid = $defaultuserfolder['folderid'];
                } else {
                    echo "<div id='UseyourDrive' class='{$colors['style']}'>";
                    $this->load_scripts('general');

                    include sprintf('%s/templates/frontend/noaccess.php', USEYOURDRIVE_ROOTDIR);
                    echo '</div>';

                    return;
                }
            }
        }

        $dataorgid = $dataid;
        $dataid = (false !== $this->options['startid']) ? $this->options['startid'] : $dataid;
        $dataaccountid = (false !== $this->options['startaccount']) ? $this->options['startaccount'] : $this->options['account'];

        $shortcode_class = ('shortcode' === $this->options['mcepopup']) ? 'initiate' : '';

        do_action('useyourdrive_before_shortcode', $this);

        echo "<div id='UseyourDrive' class='{$colors['style']} {$this->options['class']} {$this->options['mode']} {$shortcode_class}' style='display:none'>";
        echo "<noscript><div class='UseyourDrive-nojsmessage'>".__('To view this content, you need to have JavaScript enabled in your browser', 'wpcloudplugins').'.<br/>';
        echo "<a href='http://www.enable-javascript.com/' target='_blank'>".__('To do so, please follow these instructions', 'wpcloudplugins').'</a>.</div></noscript>';

        switch ($this->options['mode']) {
            case 'files':
                $this->load_scripts('files');

                echo "<div id='UseyourDrive-{$this->listtoken}' class='UseyourDrive files uyd-{$this->options['filelayout']} jsdisabled' data-list='files' data-token='{$this->listtoken}' data-account-id='{$dataaccountid}' data-id='{$dataid}' data-path='".base64_encode(json_encode($this->_folderPath))."' data-source='".md5($this->options['account'].$this->options['root'].$this->options['mode'])."' data-sort='{$this->options['sort_field']}:{$this->options['sort_order']}' data-org-id='{$dataorgid}' data-org-path='".base64_encode(json_encode($this->_folderPath))."' data-layout='{$this->options['filelayout']}' data-popout='{$this->options['can_popout']}' data-lightboxnav='{$this->options['lightbox_navigation']}' data-query='{$this->options['searchterm']}' data-type='{$this->options['mcepopup']}'>";

                if ('shortcode' === $this->options['mcepopup']) {
                    echo "<div class='selected-folder'><strong>".__('Selected folder', 'wpcloudplugins').": </strong><span class='current-folder-raw'></span></div>";
                }

                if ('linkto' === $this->get_shortcode_option('mcepopup') || 'linktobackendglobal' === $this->get_shortcode_option('mcepopup')) {
                    $rootfolder = $this->get_client()->get_root_folder();
                    $button_text = __('Use the Root Folder of your Account', 'wpcloudplugins');

                    if ('drive' !== $rootfolder->get_id()) {
                        echo '<div data-id="'.$rootfolder->get_id().'" data-name="'.$rootfolder->get_name().'">';
                        echo '<div class="entry_linkto entry_linkto_root">';
                        echo '<span><input class="button-secondary" type="submit" title="'.$button_text.'" value="'.$button_text.'"></span>';
                        echo '</div>';
                        echo '</div>';
                    }
                }

                include sprintf('%s/templates/frontend/file_browser.php', USEYOURDRIVE_ROOTDIR);
                $this->render_uploadform();

                echo '</div>';

                break;

            case 'upload':
                echo "<div id='UseyourDrive-{$this->listtoken}' class='UseyourDrive upload jsdisabled'  data-token='{$this->listtoken}' data-account-id='{$this->options['account']}' data-id='".$dataid."' data-path='".base64_encode(json_encode($this->_folderPath))."' >";
                $this->render_uploadform();
                echo '</div>';

                break;

            case 'gallery':
                $this->load_scripts('files');

                $nextimages = '';
                if (('0' !== $this->options['maximages'])) {
                    $nextimages = "data-loadimages='".$this->options['maximages']."'";
                }

                echo "<div id='UseyourDrive-{$this->listtoken}' class='UseyourDrive gridgallery jsdisabled' data-list='gallery' data-token='{$this->listtoken}' data-account-id='{$this->options['account']}' data-id='".$dataid."' data-path='".base64_encode(json_encode($this->_folderPath))."' data-sort='".$this->options['sort_field'].':'.$this->options['sort_order']."' data-org-id='".$dataid."' data-org-path='".base64_encode(json_encode($this->_folderPath))."' data-source='".md5($this->options['account'].$this->options['root'].$this->options['mode'])."' data-targetheight='".$this->options['targetheight']."' data-slideshow='".$this->options['slideshow']."' data-pausetime='".$this->options['pausetime']."' {$nextimages} data-lightboxnav='".$this->options['lightbox_navigation']."' data-query='{$this->options['searchterm']}'>";

                include sprintf('%s/templates/frontend/gallery.php', USEYOURDRIVE_ROOTDIR);
                $this->render_uploadform();
                echo '</div>';

                break;

            case 'search':
                echo "<div id='UseyourDrive-{$this->listtoken}' class='UseyourDrive files uyd-".$this->options['filelayout']." searchlist jsdisabled' data-list='search' data-token='{$this->listtoken}' data-account-id='{$this->options['account']}' data-id='".$dataid."' data-path='".base64_encode(json_encode($this->_folderPath))."' data-sort='".$this->options['sort_field'].':'.$this->options['sort_order']."' data-org-id='".$dataorgid."' data-org-path='".base64_encode(json_encode($this->_folderPath))."' data-source='".md5($this->options['account'].$this->options['root'].$this->options['mode'])."' data-layout='".$this->options['filelayout']."' data-popout='".$this->options['can_popout']."' data-lightboxnav='".$this->options['lightbox_navigation']."' data-query='{$this->options['searchterm']}'>";
                $this->load_scripts('files');

                include sprintf('%s/templates/frontend/search.php', USEYOURDRIVE_ROOTDIR);
                echo '</div>';

                break;

            case 'video':
            case 'audio':
                $mediaplayer = $this->load_mediaplayer($this->options['mediaskin']);

                echo "<div id='UseyourDrive-{$this->listtoken}' class='UseyourDrive media ".$this->options['mode']." jsdisabled' data-list='media' data-token='{$this->listtoken}' data-account-id='{$this->options['account']}' data-id='".$dataid."' data-sort='".$this->options['sort_field'].':'.$this->options['sort_order']."'>";
                $mediaplayer->load_player();
                echo '</div>';
                $this->load_scripts('mediaplayer');

                break;
        }

        echo "<script type='text/javascript'>if (typeof(jQuery) !== 'undefined' && typeof(jQuery.cp) !== 'undefined' && typeof(jQuery.cp.UseyourDrive) === 'function') { jQuery('#UseyourDrive-{$this->listtoken}').UseyourDrive(UseyourDrive_vars); };</script>";
        echo '</div>';

        do_action('useyourdrive_after_shortcode', $this);

        $this->load_scripts('general');
    }

    public function render_uploadform()
    {
        $user_can_upload = $this->get_user()->can_upload();

        if (false === $user_can_upload) {
            return;
        }

        $own_limit = ('0' !== $this->options['maxfilesize']);
        $post_max_size_bytes = min(Helpers::return_bytes(ini_get('post_max_size')), Helpers::return_bytes(ini_get('upload_max_filesize')));
        $max_file_size = ('0' !== $this->options['maxfilesize']) ? Helpers::return_bytes($this->options['maxfilesize']) : ($post_max_size_bytes);
        $min_file_size = (!empty($this->options['minfilesize'])) ? Helpers::return_bytes($this->options['minfilesize']) : '0';

        $post_max_size_str = Helpers::bytes_to_size_1024($max_file_size);
        $min_file_size_str = Helpers::bytes_to_size_1024($min_file_size);

        $acceptfiletypes = '.('.$this->options['upload_ext'].')$';
        $max_number_of_uploads = $this->options['maxnumberofuploads'];
        $upload_encryption = ('1' === $this->options['upload_encryption'] && (version_compare(phpversion(), '7.1.0', '<=')));

        $this->load_scripts('upload');

        include sprintf('%s/templates/frontend/upload_box.php', USEYOURDRIVE_ROOTDIR);
    }

    public function get_last_folder()
    {
        return $this->_lastFolder;
    }

    public function get_last_path()
    {
        return $this->_lastPath;
    }

    public function get_root_folder()
    {
        return $this->_rootFolder;
    }

    public function get_folder_path()
    {
        return $this->_folderPath;
    }

    public function get_listtoken()
    {
        return $this->listtoken;
    }

    public function load_mediaplayer($mediaplayer)
    {
        if (empty($mediaplayer)) {
            $mediaplayer = $this->get_setting('mediaplayer_skin');
        }

        if (file_exists(USEYOURDRIVE_ROOTDIR.'/skins/'.$mediaplayer.'/Player.php')) {
            require_once USEYOURDRIVE_ROOTDIR.'/skins/'.$mediaplayer.'/Player.php';
        } else {
            error_log('[WP Cloud Plugin message]: '.sprintf('Media Player Skin %s is missing', $mediaplayer));

            return $this->load_mediaplayer(null);
        }

        try {
            $class = '\TheLion\UseyourDrive\MediaPlayers\\'.$mediaplayer;

            return new $class($this);
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Media Player Skin %s is invalid', $mediaplayer));

            return false;
        }
    }

    public function sort_filelist($foldercontents)
    {
        $sort_field = 'name';
        $sort_order = SORT_ASC;

        if (count($foldercontents) > 0) {
            // Sort Filelist, folders first
            $sort = [];

            if (isset($_REQUEST['sort'])) {
                $sort_options = explode(':', $_REQUEST['sort']);

                if ('shuffle' === $sort_options[0]) {
                    shuffle($foldercontents);

                    return $foldercontents;
                }

                if (2 === count($sort_options)) {
                    switch ($sort_options[0]) {
                        case 'name':
                            $sort_field = 'name';

                            break;

                        case 'size':
                            $sort_field = 'size';

                            break;

                        case 'modified':
                            $sort_field = 'last_edited';

                            break;

                        case 'created':
                            $sort_field = 'created_time';

                            break;
                    }

                    switch ($sort_options[1]) {
                        case 'asc':
                            $sort_order = SORT_ASC;

                            break;

                        case 'desc':
                            $sort_order = SORT_DESC;

                            break;
                    }
                }
            }

            list($sort_field, $sort_order) = apply_filters('useyourdrive_sort_filelist_settings', [$sort_field, $sort_order], $foldercontents, $this);

            foreach ($foldercontents as $k => $v) {
                if ($v instanceof EntryAbstract) {
                    $sort['is_dir'][$k] = $v->is_dir();
                    $sort['sort'][$k] = strtolower($v->{'get_'.$sort_field}());
                } else {
                    $sort['is_dir'][$k] = $v['is_dir'];
                    $sort['sort'][$k] = $v[$sort_field];
                }
            }

            // Sort by dir desc and then by name asc
            array_multisort($sort['is_dir'], SORT_DESC, SORT_REGULAR, $sort['sort'], $sort_order, SORT_NATURAL, $foldercontents, SORT_ASC);
        }

        $foldercontents = apply_filters('useyourdrive_sort_filelist', $foldercontents, $sort_field, $sort_order, $this);

        return $foldercontents;
    }

    public function send_notification_email($notification_type, $entries)
    {
        $notification = new Notification($this, $notification_type, $entries);
        $notification->send_notification();
    }

    // Check if $entry is allowed

    public function _is_entry_authorized(CacheNode $cachedentry)
    {
        $entry = $cachedentry->get_entry();

        if (empty($entry)) {
            return false;
        }
        // Return in case a direct call is being made, and no shortcode is involved
        if (empty($this->options)) {
            return true;
        }

        // Action for custom filters
        $is_authorized_hook = apply_filters('useyourdrive_is_entry_authorized', true, $cachedentry, $this);
        if (false === $is_authorized_hook) {
            return false;
        }

        // Use the orginial entry if the file/folder is a shortcut
        if ($entry->is_shortcut()) {
            $original_node = $cachedentry->get_original_node();

            if (false === empty($original_node)) {
                $original_entry = $original_node->get_entry();
                if (false === empty($original_entry)) {
                    $original_entry->set_shortcut_details($cachedentry->get_entry()->get_shortcut_details());
                    $entry = $original_entry;
                }
            }
        }

        // Skip entry if its a file, and we dont want to show files
        if (($entry->is_file()) && ('0' === $this->get_shortcode_option('show_files'))) {
            return false;
        }
        // Skip entry if its a folder, and we dont want to show folders
        if (($entry->is_dir()) && ('0' === $this->get_shortcode_option('show_folders')) && ($entry->get_id() !== $this->get_requested_entry())) {
            return false;
        }

        // Only add allowed files to array
        $extension = $entry->get_extension();
        $allowed_extensions = $this->get_shortcode_option('include_ext');
        if (($entry->is_file()) && (!in_array(strtolower($extension), $allowed_extensions)) && '*' != $allowed_extensions[0]) {
            return false;
        }

        // Hide files with extensions
        $hide_extensions = $this->get_shortcode_option('exclude_ext');
        if (($entry->is_file()) && !empty($extension) && (in_array(strtolower($extension), $hide_extensions)) && '*' != $hide_extensions[0]) {
            return false;
        }

        // skip excluded folders and files
        $hide_entries = $this->get_shortcode_option('exclude');
        if ('*' != $hide_entries[0]) {
            $match = false;
            foreach ($hide_entries as $hide_entry) {
                if (fnmatch($hide_entry, $entry->get_name())) {
                    $match = true;

                    break; // Entry matches by expression (wildcards * , ?)
                }
                if ($hide_entry === $entry->get_id()) {
                    $match = true;

                    break; //Entry matches by ID
                }
            }

            if (true === $match) {
                return false;
            }
        }

        // only allow included folders and files
        $include_entries = $this->get_shortcode_option('include');
        if ('*' != $include_entries[0]) {
            if ($entry->is_dir() && (($entry->get_id() === $this->get_requested_entry() || $entry->get_id() === $this->get_root_folder()))) {
            } else {
                $match = false;
                foreach ($include_entries as $include_entry) {
                    if (fnmatch($include_entry, $entry->get_name())) {
                        $match = true;

                        break; // Entry matches by expression (wildcards * , ?)
                    }
                    if ($include_entry === $entry->get_id()) {
                        $match = true;

                        break; //Entry matches by ID
                    }
                }

                if (false === $match) {
                    return false;
                }
            }
        }

        // Make sure that files and folders from hidden folders are not allowed
        if ('*' != $hide_entries[0]) {
            foreach ($hide_entries as $hidden_entry) {
                $cached_hidden_entry = $this->get_cache()->get_node_by_name($hidden_entry);

                if (false === $cached_hidden_entry) {
                    $cached_hidden_entry = $this->get_cache()->get_node_by_id($hidden_entry);
                }

                if (false !== $cached_hidden_entry && $cached_hidden_entry->get_entry()->is_dir()) {
                    if ($cachedentry->is_in_folder($cached_hidden_entry->get_id())) {
                        return false;
                    }
                }
            }
        }

        // If only showing Shared Files
        /* if (1) {
          if ($entry->is_file()) {
          if (!$entry->getShared() && $entry->getOwnedByMe()) {
          return false;
          }
          }
          } */

        // Is entry in the selected root Folder?
        if (false === $cachedentry->is_in_folder($this->get_root_folder())) {
            return false;
        }

        return true;
    }

    public function is_filtering_entries()
    {
        if ('0' === $this->get_shortcode_option('show_files')) {
            return true;
        }

        if ('0' === $this->get_shortcode_option('show_folders')) {
            return true;
        }

        $allowed_extensions = $this->get_shortcode_option('include_ext');
        if ('*' !== $allowed_extensions[0]) {
            return true;
        }

        $hide_extensions = $this->get_shortcode_option('exclude_ext');
        if ('*' !== $hide_extensions[0]) {
            return true;
        }

        $hide_entries = $this->get_shortcode_option('exclude');
        if ('*' !== $hide_entries[0]) {
            return true;
        }
        $include_entries = $this->get_shortcode_option('include');
        if ('*' !== $include_entries[0]) {
            return true;
        }

        return false;
    }

    public function embed_image($entryid)
    {
        $cachedentry = $this->get_client()->get_entry($entryid, false);

        if (false === $cachedentry) {
            return false;
        }

        if (in_array($cachedentry->get_entry()->get_extension(), ['jpg', 'jpeg', 'gif', 'png'])) {
            // Redirect to thumbnail itself
            header("Location: https://drive.google.com/thumbnail?id={$cachedentry->get_id()}&sz=w1920");

            // Dec 2019: Google can block image downloads if it detects automated queries
            //$download = new Download($cachedentry, $this);
            //$download->start_download();
            exit();
        }

        return true;
    }

    public function set_requested_entry($entry_id)
    {
        return $this->_requestedEntry = $entry_id;
    }

    public function get_requested_entry()
    {
        return $this->_requestedEntry;
    }

    public function get_import_formats()
    {
        return [
            'application/x-vnd.oasis.opendocument.presentation' => 'application/vnd.google-apps.presentation',
            'text/tab-separated-values' => 'application/vnd.google-apps.spreadsheet',
            'image/jpeg' => 'application/vnd.google-apps.document',
            'image/bmp' => 'application/vnd.google-apps.document',
            'image/gif' => 'application/vnd.google-apps.document',
            'application/vnd.ms-excel.sheet.macroenabled.12' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'application/vnd.google-apps.document',
            'application/vnd.ms-powerpoint.presentation.macroenabled.12' => 'application/vnd.google-apps.presentation',
            'application/vnd.ms-word.template.macroenabled.12' => 'application/vnd.google-apps.document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/vnd.google-apps.document',
            'image/pjpeg' => 'application/vnd.google-apps.document',
            'application/vnd.google-apps.script+text/plain' => 'application/vnd.google-apps.script',
            'application/vnd.ms-excel' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.sun.xml.writer' => 'application/vnd.google-apps.document',
            'application/vnd.ms-word.document.macroenabled.12' => 'application/vnd.google-apps.document',
            'application/vnd.ms-powerpoint.slideshow.macroenabled.12' => 'application/vnd.google-apps.presentation',
            'text/rtf' => 'application/vnd.google-apps.document',
            'text/plain' => 'application/vnd.google-apps.document',
            'application/vnd.oasis.opendocument.spreadsheet' => 'application/vnd.google-apps.spreadsheet',
            'application/x-vnd.oasis.opendocument.spreadsheet' => 'application/vnd.google-apps.spreadsheet',
            'image/png' => 'application/vnd.google-apps.document',
            'application/x-vnd.oasis.opendocument.text' => 'application/vnd.google-apps.document',
            'application/msword' => 'application/vnd.google-apps.document',
            'application/pdf' => 'application/vnd.google-apps.document',
            'application/json' => 'application/vnd.google-apps.script',
            'application/x-msmetafile' => 'application/vnd.google-apps.drawing',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.ms-powerpoint' => 'application/vnd.google-apps.presentation',
            'application/vnd.ms-excel.template.macroenabled.12' => 'application/vnd.google-apps.spreadsheet',
            'image/x-bmp' => 'application/vnd.google-apps.document',
            'application/rtf' => 'application/vnd.google-apps.document',
            'application/vnd.openxmlformats-officedocument.presentationml.template' => 'application/vnd.google-apps.presentation',
            'image/x-png' => 'application/vnd.google-apps.document',
            'text/html' => 'application/vnd.google-apps.document',
            'application/vnd.oasis.opendocument.text' => 'application/vnd.google-apps.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'application/vnd.google-apps.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.google-apps.script+json' => 'application/vnd.google-apps.script',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'application/vnd.google-apps.presentation',
            'application/vnd.ms-powerpoint.template.macroenabled.12' => 'application/vnd.google-apps.presentation',
            'text/csv' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.oasis.opendocument.presentation' => 'application/vnd.google-apps.presentation',
            'image/jpg' => 'application/vnd.google-apps.document',
            'text/richtext' => 'application/vnd.google-apps.document',
        ];
    }

    public function is_mobile()
    {
        return $this->mobile;
    }

    public function get_setting($key, $default = null)
    {
        if (!isset($this->settings[$key])) {
            return $default;
        }

        return $this->settings[$key];
    }

    public function set_setting($key, $value)
    {
        $this->settings[$key] = $value;
        $success = update_option('use_your_drive_settings', $this->settings);
        $this->settings = get_option('use_your_drive_settings');

        return $success;
    }

    public function get_network_setting($key, $default = null)
    {
        $network_settings = get_site_option('useyourdrive_network_settings', []);

        if (!isset($network_settings[$key])) {
            return $default;
        }

        return $network_settings[$key];
    }

    public function set_network_setting($key, $value)
    {
        $network_settings = get_site_option('useyourdrive_network_settings', []);
        $network_settings[$key] = $value;

        return update_site_option('useyourdrive_network_settings', $network_settings);
    }

    public function get_shortcode()
    {
        return $this->options;
    }

    public function get_shortcode_option($key)
    {
        if (!isset($this->options[$key])) {
            return null;
        }

        return $this->options[$key];
    }

    public function set_shortcode($listtoken)
    {
        $cached_shortcode = $this->get_shortcodes()->get_shortcode_by_id($listtoken);

        if ($cached_shortcode) {
            $this->options = $cached_shortcode;
            $this->listtoken = $listtoken;
        }

        return $this->options;
    }

    public function _set_gzip_compression()
    {
        // Compress file list if possible
        if ('Yes' === $this->settings['gzipcompression']) {
            $zlib = ('' == ini_get('zlib.output_compression') || !ini_get('zlib.output_compression')) && ('ob_gzhandler' != ini_get('output_handler'));
            if (true === $zlib) {
                if (extension_loaded('zlib')) {
                    if (!in_array('ob_gzhandler', ob_list_handlers())) {
                        ob_start('ob_gzhandler');
                    }
                }
            }
        }
    }

    public function is_network_authorized()
    {
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH.'/wp-admin/includes/plugin.php';
        }

        $network_settings = get_site_option('useyourdrive_network_settings', []);

        return isset($network_settings['network_wide']) && is_plugin_active_for_network(USEYOURDRIVE_SLUG) && ('Yes' === $network_settings['network_wide']);
    }

    /**
     * @return \TheLion\UseyourDrive\Main
     */
    public function get_main()
    {
        return $this->_main;
    }

    /**
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app()
    {
        if (empty($this->_app)) {
            $this->_app = new \TheLion\UseyourDrive\App($this);

            try {
                $this->_app->start_client($this->get_current_account());
            } catch (\Exception $ex) {
                return $this->_app;
            }
        } elseif (null !== $this->get_current_account() && $this->get_current_account()->get_authorization()->has_access_token()) {
            $this->_app->get_client()->setAccessToken($this->get_current_account()->get_authorization()->get_access_token());
        }

        return $this->_app;
    }

    /**
     * @return \TheLion\UseyourDrive\Accounts
     */
    public function get_accounts()
    {
        return $this->get_main()->get_accounts();
    }

    /**
     * @return \TheLion\UseyourDrive\Account
     */
    public function get_current_account()
    {
        if (empty($this->_current_account)) {
            if (null !== $this->get_shortcode('account')) {
                $this->_current_account = $this->get_accounts()->get_account_by_id($this->get_shortcode_option('account'));
            }
        }

        return $this->_current_account;
    }

    /**
     * @return \TheLion\UseyourDrive\Account
     */
    public function set_current_account(Account $account)
    {
        $this->_current_account = $account;
    }

    public function clear_current_account()
    {
        $this->_current_account = null;
    }

    /**
     * @return \TheLion\UseyourDrive\Client
     */
    public function get_client()
    {
        if (empty($this->_client)) {
            $this->_client = new \TheLion\UseyourDrive\Client($this->get_app(), $this);
        } elseif (null !== $this->get_current_account()) {
            $this->_app->get_client()->setAccessToken($this->get_current_account()->get_authorization()->get_access_token());
        }

        return $this->_client;
    }

    /**
     * @return \TheLion\UseyourDrive\Cache
     */
    public function get_cache()
    {
        if (empty($this->_cache)) {
            $this->_cache = new \TheLion\UseyourDrive\Cache($this);
        }

        return $this->_cache;
    }

    /**
     * @return \TheLion\UseyourDrive\Shortcodes
     */
    public function get_shortcodes()
    {
        if (empty($this->_shortcodes)) {
            $this->_shortcodes = new \TheLion\UseyourDrive\Shortcodes($this);
        }

        return $this->_shortcodes;
    }

    /**
     * @param mixed $force_reload
     *
     * @return \TheLion\UseyourDrive\User
     */
    public function get_user($force_reload = false)
    {
        if (empty($this->_user) || $force_reload) {
            $this->_user = new \TheLion\UseyourDrive\User($this);
        }

        return $this->_user;
    }

    /**
     * @return \TheLion\UseyourDrive\UserFolders
     */
    public function get_user_folders()
    {
        if (empty($this->_userfolders)) {
            $this->_userfolders = new \TheLion\UseyourDrive\UserFolders($this);
        }

        return $this->_userfolders;
    }

    public function get_user_ip()
    {
        return $this->userip;
    }

    public function reset_complete_cache($including_shortcodes = false)
    {
        if (!file_exists(USEYOURDRIVE_CACHEDIR)) {
            return false;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            if ($path->isDir()) {
                continue;
            }
            if ('.htaccess' === $path->getFilename()) {
                continue;
            }

            if ('access_token' === $path->getExtension()) {
                continue;
            }

            if ('log' === $path->getExtension()) {
                continue;
            }

            if (false === $including_shortcodes && 'shortcodes' === $path->getExtension()) {
                continue;
            }

            try {
                @unlink($path->getPathname());
            } catch (\Exception $ex) {
                continue;
            }
        }

        return true;
    }

    public function do_shutdown()
    {
        $error = error_get_last();

        if (null === $error) {
            return;
        }

        if (E_ERROR !== $error['type']) {
            return;
        }

        if (isset($error['file']) && false !== strpos($error['file'], USEYOURDRIVE_ROOTDIR)) {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-unknown-';
            error_log('[WP Cloud Plugin message]: Complete reset; URL: '.$url.';Reason: '.var_export($error, true));

            // fatal error has occured
            $this->get_cache()->reset_cache();
        }
    }

    protected function set_last_path($path)
    {
        $this->_lastPath = $path;
        if ('' === $this->_lastPath) {
            $this->_lastPath = null;
        }

        return $this->_lastPath;
    }

    protected function load_scripts($template)
    {
        if (true === $this->_load_scripts[$template]) {
            return false;
        }

        switch ($template) {
            case 'general':
                if (false === defined('WPCP_DISABLE_FONTAWESOME')) {
                    wp_enqueue_style('Awesome-Font-5-css');
                    if ('Yes' === $this->get_setting('fontawesomev4_shim')) {
                        wp_enqueue_style('Awesome-Font-4-shim-css');
                    }
                }

                wp_enqueue_style('UseyourDrive');
                wp_enqueue_script('UseyourDrive');

                add_action('wp_footer', [$this->get_main(), 'load_custom_css'], 100);
                add_action('admin_footer', [$this->get_main(), 'load_custom_css'], 100);

                break;

            case 'files':
                if ($this->get_user()->can_move_files() || $this->get_user()->can_move_folders()) {
                    wp_enqueue_script('jquery-ui-droppable');
                    wp_enqueue_script('jquery-ui-draggable');
                }

                wp_enqueue_script('jquery-effects-core');
                wp_enqueue_script('jquery-effects-fade');
                wp_enqueue_style('ilightbox');
                wp_enqueue_style('ilightbox-skin-useyourdrive');
                wp_enqueue_script('google-recaptcha');

                break;

            case 'mediaplayer':
                break;

            case 'upload':
                wp_enqueue_script('jquery-ui-droppable');
                wp_enqueue_script('jquery-ui-button');
                wp_enqueue_script('jquery-ui-progressbar');
                wp_enqueue_script('jQuery.iframe-transport');
                wp_enqueue_script('jQuery.fileupload-uyd');
                wp_enqueue_script('jQuery.fileupload-process');
                wp_enqueue_script('UseyourDrive.UploadBox');
                wp_enqueue_script('google-recaptcha');

                Helpers::append_dependency('UseyourDrive', 'UseyourDrive.UploadBox');
                Helpers::append_dependency('UseyourDrive', 'UseyourDrive.UploadBox');
                Helpers::append_dependency('UseyourDrive', 'UseyourDrive.UploadBox');

                break;
        }

        $this->_load_scripts[$template] = true;
    }

    protected function remove_deprecated_options($options = [])
    {
        // Deprecated Shuffle, v1.3
        if (isset($options['shuffle'])) {
            unset($options['shuffle']);
            $options['sortfield'] = 'shuffle';
        }
        // Changed Userfolders, v1.3
        if (isset($options['userfolders']) && '1' === $options['userfolders']) {
            $options['userfolders'] = 'auto';
        }

        if (isset($options['partiallastrow'])) {
            unset($options['partiallastrow']);
        }

        // Changed Rename/Delete/Move Folders & Files v1.5.2
        if (isset($options['move_role'])) {
            $options['move_files_role'] = $options['move_role'];
            $options['move_folders_role'] = $options['move_role'];
            unset($options['move_role']);
        }

        if (isset($options['rename_role'])) {
            $options['rename_files_role'] = $options['rename_role'];
            $options['rename_folders_role'] = $options['rename_role'];
            unset($options['rename_role']);
        }

        if (isset($options['delete_role'])) {
            $options['delete_files_role'] = $options['delete_role'];
            $options['delete_folders_role'] = $options['delete_role'];
            unset($options['delete_role']);
        }

        // Changed 'ext' to 'include_ext' v1.5.2
        if (isset($options['ext'])) {
            $options['include_ext'] = $options['ext'];
            unset($options['ext']);
        }

        if (isset($options['maxfiles']) && empty($options['maxfiles'])) {
            unset($options['maxfiles']);
        }

        // Convert bytes in version before 1.8 to MB
        if (isset($options['maxfilesize']) && !empty($options['maxfilesize']) && ctype_digit($options['maxfilesize'])) {
            $options['maxfilesize'] = Helpers::bytes_to_size_1024($options['maxfilesize']);
        }

        // Changed 'covers' to 'playlistthumbnails'
        if (isset($options['covers'])) {
            $options['playlistthumbnails'] = $options['covers'];
            unset($options['covers']);
        }

        // Changed default shortcode options for forms
        if (
            (isset($options['class']) && !isset($options['upload_auto_start']))
            && (
                false !== strpos($options['class'], 'cf7_upload_box')
                || false !== strpos($options['class'], 'gf_upload_box')
                || false !== strpos($options['class'], 'wpform_upload_box')
                || false !== strpos($options['class'], 'formidableforms_upload_box')
                || false !== strpos($options['class'], 'ninjaforms_upload_box')
            )
        ) {
            $options['upload_auto_start'] = '0';
        }

        return $options;
    }

    protected function save_shortcodes()
    {
        $this->get_shortcodes()->set_shortcode($this->listtoken, $this->options);
        $this->get_shortcodes()->update_cache();
    }

    private function _is_action_authorized($hook = false)
    {
        $nonce_verification = ('Yes' === $this->get_setting('nonce_validation'));
        $allow_nonce_verification = apply_filters('use_your_drive_allow_nonce_verification', $nonce_verification);

        if ($allow_nonce_verification && isset($_REQUEST['action']) && (false === $hook) && is_user_logged_in()) {
            $is_authorized = false;

            switch ($_REQUEST['action']) {
                case 'useyourdrive-upload-file':
                case 'useyourdrive-get-filelist':
                case 'useyourdrive-get-gallery':
                case 'useyourdrive-get-playlist':
                case 'useyourdrive-rename-entry':
                case 'useyourdrive-copy-entry':
                case 'useyourdrive-move-entries':
                case 'useyourdrive-edit-description-entry':
                case 'useyourdrive-create-entry':
                case 'useyourdrive-create-zip':
                case 'useyourdrive-delete-entries':
                case 'useyourdrive-event-log':
                    $is_authorized = check_ajax_referer($_REQUEST['action'], false, false);

                    break;

                case 'useyourdrive-create-link':
                case 'useyourdrive-embedded':
                    $is_authorized = check_ajax_referer('useyourdrive-create-link', false, false);

                    break;

                case 'useyourdrive-reset-cache':
                case 'useyourdrive-factory-reset':
                case 'useyourdrive-reset-statistics':
                    $is_authorized = check_ajax_referer('useyourdrive-admin-action', false, false);

                    break;

                case 'useyourdrive-revoke':
                    return false !== check_ajax_referer('useyourdrive-admin-action', false, false);

                    break;

                case 'useyourdrive-download':
                case 'useyourdrive-stream':
                case 'useyourdrive-preview':
                case 'useyourdrive-thumbnail':
                case 'useyourdrive-edit':
                case 'useyourdrive-getpopup':
                    $is_authorized = true;

                    break;

                case 'edit': // Required for integration one Page/Post pages
                    $is_authorized = true;

                    break;

                case 'editpost': // Required for Yoast SEO Link Watcher trying to build the shortcode
                case 'wpseo_filter_shortcodes':
                case 'elementor':
                case 'elementor_ajax':
                    return false;

                default:
                    error_log('[WP Cloud Plugin message]: '." Function _is_action_authorized() didn't receive a valid action: ".$_REQUEST['action']);

                    exit();
            }

            if (false === $is_authorized) {
                error_log('[WP Cloud Plugin message]: '." Function _is_action_authorized() didn't receive a valid nonce");

                exit();
            }
        }

        return true;
    }
}
