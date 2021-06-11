<?php

namespace TheLion\UseyourDrive;

class Filebrowser
{
    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;
    private $_search = false;
    private $_parentfolders = [];

    public function __construct(Processor $_processor)
    {
        $this->_processor = $_processor;
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        return $this->_processor;
    }

    public function get_files_list()
    {
        $this->_folder = $this->get_processor()->get_client()->get_folder();

        if ((false !== $this->_folder)) {
            $this->filesarray = $this->createFilesArray();
            $this->renderFilelist();
        } else {
            exit('Folder is not received');
        }
    }

    public function search_files()
    {
        $this->_search = true;
        $input = $_REQUEST['query'];
        $this->_folder['folder'] = $this->get_processor()->get_client()->get_entry($this->get_processor()->get_root_folder());
        $this->_folder['contents'] = $this->get_processor()->get_client()->search_by_name($input);

        if ((false !== $this->_folder)) {
            $this->filesarray = $this->createFilesArray();
            $this->renderFilelist();
        }
    }

    public function setFolder($folder)
    {
        $this->_folder = $folder;
    }

    public function setParentFolder()
    {
        if (true === $this->_search) {
            return;
        }

        $currentfolder = $this->_folder['folder']->get_entry()->get_id();
        if ($currentfolder !== $this->get_processor()->get_root_folder()) {
            // Get parent folder from known folder path
            $cacheparentfolder = $this->get_processor()->get_client()->get_folder($this->get_processor()->get_root_folder());
            $folder_path = $this->get_processor()->get_folder_path();
            $parentid = end($folder_path);
            if (false !== $parentid) {
                $cacheparentfolder = $this->get_processor()->get_client()->get_folder($parentid);
            }

            /* Check if parent folder indeed is direct parent of entry
             * If not, return all known parents */
            $parentfolders = [];
            if (false !== $cacheparentfolder && $cacheparentfolder['folder']->has_children() && array_key_exists($currentfolder, $cacheparentfolder['folder']->get_children())) {
                $parentfolders[] = $cacheparentfolder['folder']->get_entry();
            } else {
                if ($this->_folder['folder']->has_parents()) {
                    foreach ($this->_folder['folder']->get_parents() as $parent) {
                        $parentfolders[] = $parent->get_entry();
                    }
                }
            }
            $this->_parentfolders = $parentfolders;
        }
    }

    public function renderFilelist()
    {
        // Create HTML Filelist
        $filelist_html = '';

        $breadcrumb_class = ('1' === $this->get_processor()->get_shortcode_option('show_breadcrumb')) ? 'has-breadcrumb' : 'no-breadcrumb';

        $filelist_html = "<div class='files {$breadcrumb_class}'>";
        $filelist_html .= "<div class='folders-container'>";

        $hasfilesorfolders = false;

        if (count($this->filesarray) > 0) {
            // Limit the number of files if needed
            if ('-1' !== $this->get_processor()->get_shortcode_option('max_files')) {
                $this->filesarray = array_slice($this->filesarray, 0, $this->get_processor()->get_shortcode_option('max_files'));
            }

            foreach ($this->filesarray as $item) {
                // Render folder div
                if ($item->is_dir()) {
                    $filelist_html .= $this->renderDir($item);

                    if (false === $item->is_parent_folder()) {
                        $hasfilesorfolders = true;
                    }
                }
            }
        }

        if (false === $this->_search && (false === $this->_folder['folder']->get_entry()->is_special_folder() || 'mydrive' === $this->_folder['folder']->get_entry()->get_special_folder())) {
            $filelist_html .= $this->renderNewFolder();
        }
        $filelist_html .= "</div><div class='files-container'>";

        if (count($this->filesarray) > 0) {
            foreach ($this->filesarray as $item) {
                // Render files div
                if ($item->is_file()) {
                    $filelist_html .= $this->renderFile($item);
                    $hasfilesorfolders = true;
                }
            }

            if (false === $hasfilesorfolders) {
                if ('1' === $this->get_processor()->get_shortcode_option('show_files')) {
                    $filelist_html .= $this->renderNoResults();
                }
            }
        } else {
            if ('1' === $this->get_processor()->get_shortcode_option('show_files') || true === $this->_search) {
                $filelist_html .= $this->renderNoResults();
            }
        }

        $filelist_html .= '</div></div>';

        // Create HTML Filelist title
        $file_path = '<ol class="wpcp-breadcrumb">';
        $folder_path = $this->get_processor()->get_folder_path();
        $root_folder_id = $this->get_processor()->get_root_folder();
        if (!isset($this->_folder['folder'])) {
            $this->_folder['folder'] = $this->get_processor()->get_client()->get_entry($this->get_processor()->get_requested_entry());
        }

        $current_id = $this->_folder['folder']->get_entry()->get_id();
        $current_folder_name = $this->_folder['folder']->get_entry()->get_name();

        if ($root_folder_id === $current_id) {
            $file_path .= "<li class='first-breadcrumb'><a href='#{$current_id}' class='folder current_folder' data-id='".$current_id."'>".$this->get_processor()->get_shortcode_option('root_text').'</a></li>';
        } elseif (false === $this->_search || 'parent' === $this->get_processor()->get_shortcode_option('searchfrom')) {
            foreach ($folder_path as $parent_id) {
                if ($parent_id === $root_folder_id) {
                    $file_path .= "<li class='first-breadcrumb'><a href='#{$parent_id}' class='folder' data-id='".$parent_id."'>".$this->get_processor()->get_shortcode_option('root_text').'</a></li>';
                } else {
                    $parent_folder = $this->get_processor()->get_client()->get_folder($parent_id);
                    $file_path .= "<li><a href='#{$parent_id}' class='folder' data-id='".$parent_id."'>".$parent_folder['folder']->get_name().'</a></li>';
                }
            }
            $file_path .= "<li><a href='#{$current_id}' class='folder current_folder' data-id='".$current_id."'>".$current_folder_name.'</a></li>';
        }

        if (true === $this->_search) {
            $file_path .= "<li><a href='javascript:void(0)' class='folder'>".sprintf(__('Results for %s', 'wpcloudplugins'), "'".$_REQUEST['query']."'").'</a></li>';
        }

        $file_path .= '</ol>';

        $raw_path = '';
        if ((true !== $this->_search) && (current_user_can('edit_posts') || current_user_can('edit_pages')) && ('true' == get_user_option('rich_editing'))) {
            $raw_path = $this->_folder['folder']->get_entry()->get_name();
        }

        // lastFolder contains current folder path of the user
        if (true !== $this->_search && (end($folder_path) !== $this->_folder['folder']->get_entry()->get_id())) {
            $folder_path[] = $this->_folder['folder']->get_entry()->get_id();
        }

        if (true === $this->_search) {
            $lastFolder = $this->get_processor()->get_last_folder();
            $expires = 0;
        } else {
            $lastFolder = $this->_folder['folder']->get_entry()->get_id();
            $expires = $this->_folder['folder']->get_expired();
        }

        $response = json_encode([
            'rawpath' => $raw_path,
            'folderPath' => base64_encode(json_encode($folder_path)),
            'accountId' => $this->_folder['folder']->get_account_id(),

            'virtual' => false === $this->_search
            && $this->_folder['folder']->get_entry()->is_special_folder()
            && 'mydrive' !== $this->_folder['folder']->get_entry()->get_special_folder()
            && 'shared-drive' !== $this->_folder['folder']->get_entry()->get_special_folder(),

            'lastFolder' => $lastFolder,
            'breadcrumb' => $file_path,
            'html' => $filelist_html,
            'hasChanges' => defined('HAS_CHANGES'),
            'expires' => $expires, ]);

        if (false === defined('HAS_CHANGES')) {
            $cached_request = new CacheRequest($this->get_processor());
            $cached_request->add_cached_response($response);
        }

        echo $response;

        exit();
    }

    public function renderNoResults()
    {
        $icon_set = $this->get_processor()->get_setting('loaders');

        $html = "<div class='entry file no-entries'>\n";
        $html .= "<div class='entry_block'>\n";
        $html .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $html .= "<a class='entry_link'><img class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$icon_set['no_results']."' data-src-retina='".$icon_set['no_results']."'/></a>";
        $html .= "</div></div></div>\n";

        $html .= "<div class='entry-info'>";
        $html .= "<div class='entry-info-name'>";
        $html .= "<a class='entry_link' title='".__('This folder is empty', 'wpcloudplugins')."'><div class='entry-name-view'>";
        $html .= '<span>'.__('This folder is empty', 'wpcloudplugins').'</span>';
        $html .= '</div></a>';
        $html .= "</div>\n";

        $html .= "</div>\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    public function renderDir(EntryAbstract $item)
    {
        $return = '';

        $classmoveable = ($this->get_processor()->get_user()->can_move_folders()) ? 'moveable' : '';
        $classshortcut = ($item->is_shortcut()) ? 'isshortcut' : '';

        $isparent = (isset($this->_folder['folder'])) ? $this->_folder['folder']->is_in_folder($item->get_id()) : false;

        $return .= "<div class='entry {$classmoveable} {$classshortcut} folder ".($isparent ? 'pf' : '')."' data-id='".$item->get_id()."' data-name='".htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, 'UTF-8')."'>\n";
        if (!$isparent) {
            if ('linkto' === $this->get_processor()->get_shortcode_option('mcepopup') || 'linktobackendglobal' === $this->get_processor()->get_shortcode_option('mcepopup')) {
                $return .= "<div class='entry_linkto'>\n";
                $return .= '<span>'."<input class='button-secondary' type='submit' title='".__('Select folder', 'wpcloudplugins')."' value='".__('Select folder', 'wpcloudplugins')."'>".'</span>';
                $return .= '</div>';
            }

            if ('woocommerce' === $this->get_processor()->get_shortcode_option('mcepopup')) {
                $return .= "<div class='entry_woocommerce_link'>\n";
                $return .= '<span>'."<input class='button-secondary' type='button' title='".__('Select folder', 'wpcloudplugins')."' value='".__('Select folder', 'wpcloudplugins')."'>".'</span>';
                $return .= '</div>';
            }
        }

        $return .= "<div class='entry_block'>\n";

        $return .= "<div class='entry-info'>";

        $thumburl = $isparent ? USEYOURDRIVE_ICON_SET.'/prev.png' : $item->get_thumbnail_small();
        $return .= "<div class='entry-info-icon'><div class='preloading'></div><img class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='{$thumburl}' data-src-retina='{$thumburl}'/></div>";

        $return .= "<div class='entry-info-name'>";
        $return .= "<a class='entry_link' title='{$item->get_basename()}'>";
        $return .= '<span>';
        $return .= (($isparent) ? '<strong>'.__('Previous folder', 'wpcloudplugins').'</strong>' : $item->get_name()).' </span>';
        $return .= '</span>';
        $return .= '</a></div>';

        if (!$isparent) {
            $return .= $this->renderDescription($item);
            $return .= $this->renderActionMenu($item);
            $return .= $this->renderCheckBox($item);
        }

        $return .= "</div>\n";

        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderFile(EntryAbstract $item)
    {
        $link = $this->renderFileNameLink($item);
        $title = $link['filename'].((('1' === $this->get_processor()->get_shortcode_option('show_filesize')) && ($item->get_size() > 0)) ? ' ('.Helpers::bytes_to_size_1024($item->get_size()).')' : '&nbsp;');

        $classmoveable = ($this->get_processor()->get_user()->can_move_files()) ? 'moveable' : '';
        $classshortcut = ($item->is_shortcut()) ? 'isshortcut' : '';

        $thumbnail_small = (false === strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $item->get_thumbnail_with_size('w500-h375-p-k') : $item->get_thumbnail_small().'&account_id='.$this->_folder['folder']->get_account_id().'&listtoken='.$this->get_processor()->get_listtoken();
        $has_tooltip = ($item->has_own_thumbnail() && !empty($thumbnail_small) && ('shortcode' !== $this->get_processor()->get_shortcode_option('mcepopup')) ? "data-tooltip=''" : '');

        $return = '';
        $return .= "<div class='entry file {$classmoveable} {$classshortcut}' data-id='".$item->get_id()."' data-name='".htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, 'UTF-8')."' {$has_tooltip}>\n";
        $return .= "<div class='entry_block'>\n";

        $caption = '<span data-id="'.$item->get_id().'"></span>';

        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $return .= "<div class='preloading'></div>";
        $return .= "<img referrerpolicy='no-referrer' class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$thumbnail_small."' data-src-retina='".$thumbnail_small."' data-src-backup='".str_replace('/64/', '/256/', $item->get_icon())."'/>";
        $return .= "</div></div></div>\n";

        if ($duration = $item->get_media('duration')) {
            $return .= "<div class='entry-duration'><i class='fas fa-play fa-xs'></i> ".Helpers::convert_ms_to_time($duration).'</div>';
        }

        $return .= "<div class='entry-info'>";
        $return .= "<div class='entry-info-icon'><img src='".$item->get_icon()."'/></div>";
        $return .= "<div class='entry-info-name'>";
        $return .= '<a '.$link['url'].' '.$link['target']." class='entry_link ".$link['class']."' ".$link['onclick']." title='".$title."' ".$link['lightbox']." data-filename='".$link['filename']."' data-caption='{$caption}' {$link['extra_attr']} >";
        $return .= '<span>'.($item->is_shortcut() ? '<i class="fas fa-share"></i>&nbsp;' : '').$link['filename'].'</span>';
        $return .= '</a>';

        if (('shortcode' === $this->get_processor()->get_shortcode_option('mcepopup')) && (in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'oga', 'wav', 'webm']))) {
            $return .= "&nbsp;<a class='entry_media_shortcode'><i class='fas fa-code'></i></a>";
        }

        $return .= '</div>';

        $return .= $this->renderModifiedDate($item);
        $return .= $this->renderSize($item);
        $return .= $this->renderDescription($item);
        $return .= $this->renderActionMenu($item);
        $return .= $this->renderCheckBox($item);

        $return .= "</div>\n";

        $return .= $link['lightbox_inline'];

        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderSize(EntryAbstract $item)
    {
        if ('1' === $this->get_processor()->get_shortcode_option('show_filesize')) {
            $size = ($item->get_size() > 0) ? Helpers::bytes_to_size_1024($item->get_size()) : '&nbsp;';

            return "<div class='entry-info-size entry-info-metadata'>".$size.'</div>';
        }
    }

    public function renderModifiedDate(EntryAbstract $item)
    {
        if ('1' === $this->get_processor()->get_shortcode_option('show_filedate')) {
            return "<div class='entry-info-modified-date entry-info-metadata'>".$item->get_last_edited_str().'</div>';
        }
    }

    public function renderCheckBox(EntryAbstract $item)
    {
        $checkbox = '';

        if ($item->is_dir()) {
            if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_folders() || $this->get_processor()->get_user()->can_move_folders()) {
                $checkbox .= "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'/><label for='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'></label></div>";
            }

            if ((in_array($this->get_processor()->get_shortcode_option('mcepopup'), ['links', 'embedded']))) {
                $checkbox .= "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'/><label for='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'></label></div>";
            }
        } else {
            if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_files() || $this->get_processor()->get_user()->can_move_files()) {
                $checkbox .= "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'/><label for='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'></label></div>";
            }

            if ((in_array($this->get_processor()->get_shortcode_option('mcepopup'), ['links', 'embedded']))) {
                $checkbox .= "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'/><label for='checkbox-{$this->get_processor()->get_listtoken()}-{$item->get_id()}'></label></div>";
            }
        }

        return $checkbox;
    }

    public function renderFileNameLink(EntryAbstract $item)
    {
        $class = '';
        $url = '';
        $target = '';
        $onclick = '';
        $datatype = 'iframe';
        $lightbox_inline = '';
        $extra_attr = '';

        $permissions = $item->get_permissions();
        $usercanpreview = ($permissions['canpreview']) && '0' === $this->get_processor()->get_shortcode_option('forcedownload');
        $usercanread = $this->get_processor()->get_user()->can_download();

        // If we don't need to create a link
        if (('0' !== $this->get_processor()->get_shortcode_option('mcepopup')) || (!$usercanpreview)) {
            if ($usercanread) {
                $url = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-download&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$item->get_id().'&dl=1&listtoken='.$this->get_processor()->get_listtoken();
                $class = 'entry_action_download';
                $extra_attr = "download='{$item->get_name()}'";
            }

            if ('woocommerce' === $this->get_processor()->get_shortcode_option('mcepopup')) {
                $class = 'entry_woocommerce_link';
            }

            // No Url
        } elseif ($usercanread && '1' === $this->get_processor()->get_shortcode_option('forcedownload')) {
            // If is set to force download
            $url = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-download&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$item->get_id().'&dl=1&listtoken='.$this->get_processor()->get_listtoken();
            $class = 'entry_action_download';
            $extra_attr = "download='{$item->get_name()}'";
        } elseif (($usercanread && !$item->get_can_preview_by_cloud())) {
            // If the file doesn't have a preview
            $url = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-download&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$item->get_id().'&dl=1&listtoken='.$this->get_processor()->get_listtoken();
            $class = 'entry_action_download';
            $extra_attr = "download='{$item->get_name()}'";

            // If file is image
            if (in_array($item->get_extension(), ['jpg', 'jpeg', 'gif', 'png'])) {
                $class = 'ilightbox-group';
                $datatype = 'image';
                $extra_attr = '';

                if ('googlethumbnail' === $this->get_processor()->get_setting('loadimages')) {
                    $url = $item->get_thumbnail_large();
                }
            } elseif (in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga'])) {
                //$datatype = 'inline';
                //$url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-stream&account_id={$this->get_processor()->get_current_account()->get_id()}&id=" . $item->get_id() . "&dl=0&listtoken=" . $this->get_processor()->get_listtoken();
            }
        } elseif ($usercanpreview && $item->get_can_preview_by_cloud()) {
            // If user can't dowload a file or can preview and file can be previewd
            $url = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-preview&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".urlencode($item->get_id()).'&listtoken='.$this->get_processor()->get_listtoken();
            $onclick = "sendDriveGooglePageView('Preview', '".$item->get_basename().((!empty($item->extension)) ? '.'.$item->get_extension() : '')."');";
            $class = 'ilightbox-group';

            // If file is image
            if (in_array($item->get_extension(), ['jpg', 'jpeg', 'gif', 'png'])) {
                $datatype = 'image';

                if ('googlethumbnail' === $this->get_processor()->get_setting('loadimages')) {
                    $url = $item->get_thumbnail_large();
                }
            } elseif (in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga'])) {
                //$datatype = 'inline';
                //$url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-stream&account_id={$this->get_processor()->get_current_account()->get_id()}&id=" . $item->get_id() . "&dl=0&listtoken=" . $this->get_processor()->get_listtoken();
            }

            // Overwrite if preview inline is disabled
            if ('0' === $this->get_processor()->get_shortcode_option('previewinline')) {
                $onclick = "sendDriveGooglePageView('Preview (new window)', '".$item->get_basename().((!empty($item->extension)) ? '.'.$item->get_extension() : '')."');";
                $class = 'entry_action_external_view';
                $target = '_blank';
            }
        }
        // No Url

        $filename = $item->get_basename();
        $filename .= (('1' === $this->get_processor()->get_shortcode_option('show_ext') && !empty($item->extension)) ? '.'.$item->get_extension() : '');

        // Lightbox Settings
        $lightbox = "rel='ilightbox[".$this->get_processor()->get_listtoken()."]' ";
        $lightbox .= 'data-type="'.$datatype.'"';

        $thumbnail_small = (false === strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $item->get_thumbnail_small() : $item->get_thumbnail_small().'&account_id='.$this->_folder['folder']->get_account_id().'&listtoken='.$this->get_processor()->get_listtoken();
        if ('iframe' === $datatype) {
            $lightbox .= 'data-options="thumbnail: \''.$thumbnail_small.'\', width: \'85%\', height: \'80%\', mousewheel: false"';
        } elseif ('inline' === $datatype) {
            $id = 'ilightbox_'.$this->get_processor()->get_listtoken().'_'.md5($item->get_id());
            $html5_element = (false === strpos($item->get_mimetype(), 'video')) ? 'audio' : 'video';

            $lightbox_size = (false !== strpos($item->get_mimetype(), 'audio')) ? 'width: \'85%\',' : 'width: \'85%\', height: \'85%\',';
            $lightbox .= ' data-options="mousewheel: false, swipe:false, '.$lightbox_size.' thumbnail: \''.$thumbnail_small.'\'"';

            $download = 'controlsList="nodownload"';
            $lightbox_inline = '<div id="'.$id.'" class="html5_player" style="display:none;"><'.$html5_element.' controls '.$download.' preload="metadata"  poster="'.$item->get_thumbnail_large().'"> <source data-src="'.$url.'" type="'.$item->get_mimetype().'">'.__('Your browser does not support HTML5. You can only download this file', 'wpcloudplugins').'</'.$html5_element.'></div>';
            $url = '#'.$id;
        } else {
            $lightbox .= 'data-options="thumbnail: \''.$thumbnail_small.'\'"';
        }

        if ('shortcode' === $this->get_processor()->get_shortcode_option('mcepopup')) {
            $url = '';
        }

        if (!empty($url)) {
            $url = "href='".$url."'";
        }
        if (!empty($target)) {
            $target = "target='".$target."'";
        }
        if (!empty($onclick)) {
            $onclick = 'onclick="'.$onclick.'"';
        }

        // Return Values
        return ['filename' => htmlspecialchars($filename, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, 'UTF-8'), 'class' => $class, 'url' => $url, 'lightbox' => $lightbox, 'lightbox_inline' => $lightbox_inline, 'target' => $target, 'onclick' => $onclick, 'extra_attr' => $extra_attr];
    }

    public function renderDescription(EntryAbstract $item)
    {
        $html = '';

        if ($item->is_special_folder()) {
            return $html;
        }

        $has_description = (false === empty($item->description));

        $metadata = [
            'modified' => "<i class='fas fa-history'></i> ".$item->get_last_edited_str(),
            'size' => ($item->get_size() > 0) ? Helpers::bytes_to_size_1024($item->get_size()) : '',
        ];

        $html .= "<div class='entry-info-button entry-description-button ".(($has_description) ? '-visible' : '')."' tabindex='0'><i class='fas fa-info-circle'></i>\n";
        $html .= "<div class='tippy-content-holder'>";
        $html .= "<div class='description-textbox'>";
        $html .= ($has_description) ? "<div class='description-text'>".nl2br($item->get_description()).'</div>' : '';
        $html .= "<div class='description-file-info'>".implode(' &bull; ', array_filter($metadata)).'</div>';

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function renderActionMenu(EntryAbstract $item)
    {
        $html = '';

        if ($item->is_special_folder()) {
            return $html;
        }

        $permissions = $item->get_permissions();

        $usercanpreview = $permissions['canpreview'];
        $usercanshare = $permissions['canshare'] && $this->get_processor()->get_user()->can_share();
        $usercanread = $this->get_processor()->get_user()->can_download();
        $usercanedit = $this->get_processor()->get_user()->can_edit();
        $usercaneditdescription = $this->get_processor()->get_user()->can_edit_description();
        $usercandeeplink = $this->get_processor()->get_user()->can_deeplink();
        $usercanrename = $permissions['canrename'] && ($item->is_dir()) ? $this->get_processor()->get_user()->can_rename_folders() : $this->get_processor()->get_user()->can_rename_files();
        $usercanmove = $permissions['canmove'] && (($item->is_dir()) ? $this->get_processor()->get_user()->can_move_folders() : $this->get_processor()->get_user()->can_move_files());
        $usercancopy = (($item->is_dir()) ? $this->get_processor()->get_user()->can_copy_folders() : $this->get_processor()->get_user()->can_copy_files());
        $usercandelete = $permissions['candelete'] && (($item->is_dir()) ? $this->get_processor()->get_user()->can_delete_folders() : $this->get_processor()->get_user()->can_delete_files());

        $filename = $item->get_basename();
        $filename .= (('1' === $this->get_processor()->get_shortcode_option('show_ext') && !empty($item->extension)) ? '.'.$item->get_extension() : '');

        // View
        if (($usercanpreview) && '1' !== $this->get_processor()->get_shortcode_option('forcedownload') && ($item->is_file()) && !('zip' === $item->get_extension())) {
            if (('1' === $this->get_processor()->get_shortcode_option('previewinline'))) {
                $html .= "<li><a class='entry_action_view' title='".__('Preview', 'wpcloudplugins')."'><i class='fas fa-eye'></i>&nbsp;".__('Preview', 'wpcloudplugins').'</a></li>';
            }

            if ($item->get_can_preview_by_cloud() && $usercanread) {
                $url = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-preview&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".urlencode($item->get_id()).'&listtoken='.$this->get_processor()->get_listtoken();
                $onclick = "sendDriveGooglePageView('Preview (new window)', '".$item->get_basename().((!empty($item->extension)) ? '.'.$item->get_extension() : '')."');";
                $html .= "<li><a href='{$url}' target='_blank' class='entry_action_external_view' onclick=\"{$onclick}\" title='".__('Preview in new window', 'wpcloudplugins')."'><i class='fas fa-desktop'></i>&nbsp;".__('Preview in new window', 'wpcloudplugins').'</a></li>';
            }
        }

        // Deeplink
        if ($usercandeeplink) {
            $html .= "<li><a class='entry_action_deeplink' title='".__('Direct link', 'wpcloudplugins')."'><i class='fas fa-link'></i>&nbsp;".__('Direct link', 'wpcloudplugins').'</a></li>';
        }

        // Shortlink
        if ($usercanshare) {
            $html .= "<li><a class='entry_action_shortlink' title='".__('Share', 'wpcloudplugins')."'><i class='fas fa-share-alt'></i>&nbsp;".__('Share', 'wpcloudplugins').'</a></li>';
        }

        // Download
        if (($usercanread) && ($item->is_file()) && (0 === count($item->get_save_as()))) {
            $html .= "<li><a href='".USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-download&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$item->get_id().'&dl=1&listtoken='.$this->get_processor()->get_listtoken()."' class='entry_action_download' download='".$item->get_name()."' data-filename='".$filename."' title='".__('Download', 'wpcloudplugins')."'><i class='fas fa-arrow-down'></i>&nbsp;".__('Download', 'wpcloudplugins').'</a></li>';
        } elseif (($usercanread) && ($item->is_file()) && (count($item->get_save_as()) > 0)) {
            // Exportformats
            if (count($item->get_save_as()) > 0) {
                foreach ($item->get_save_as() as $name => $exportlinks) {
                    $html .= "<li><a href='".USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-download&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$item->get_id().'&dl=1&mimetype='.$exportlinks['mimetype'].'&extension='.$exportlinks['extension'].'&listtoken='.$this->get_processor()->get_listtoken()."' class='entry_action_export' download='".$item->get_name()."' data-filename='".$filename."'><i class='fa ".$exportlinks['icon']."'></i>&nbsp;".__('Download as', 'wpcloudplugins').' '.$name.'</a>';
                }
            }
        }

        if ($usercanread && $item->is_dir() && '1' === $this->get_processor()->get_shortcode_option('can_download_zip')) {
            $html .= "<li><a class='entry_action_download' download='".$item->get_name()."' data-filename='".$filename."' title='".__('Download', 'wpcloudplugins')."'><i class='fas fa-arrow-down'></i>&nbsp;".__('Download', 'wpcloudplugins').'</a></li>';
        }

        // Edit
        if (($usercanedit) && ($item->is_file()) && $item->get_can_edit_by_cloud()) {
            $html .= "<li><a href='".USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-edit&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$item->get_id().'&listtoken='.$this->get_processor()->get_listtoken()."' target='_blank' class='entry_action_edit' data-filename='".$filename."' title='".__('Edit (new window)', 'wpcloudplugins')."'><i class='fas fa-pen-square'></i>&nbsp;".__('Edit (new window)', 'wpcloudplugins').'</a></li>';
        }

        // Descriptions
        if ($usercaneditdescription) {
            if (empty($item->description)) {
                $html .= "<li><a class='entry_action_description' title='".__('Add description', 'wpcloudplugins')."'><i class='fas fa-comment-alt'></i>&nbsp;".__('Add description', 'wpcloudplugins').'</a></li>';
            } else {
                $html .= "<li><a class='entry_action_description' title='".__('Edit description', 'wpcloudplugins')."'><i class='fas fa-comment-alt'></i>&nbsp;".__('Edit description', 'wpcloudplugins').'</a></li>';
            }
        }

        // Rename
        if ($usercanrename) {
            $html .= "<li><a class='entry_action_rename' title='".__('Rename', 'wpcloudplugins')."'><i class='fas fa-tag'></i>&nbsp;".__('Rename', 'wpcloudplugins').'</a></li>';
        }

        // Move
        if ($usercanmove) {
            $html .= "<li><a class='entry_action_move' title='".__('Move to', 'wpcloudplugins')."'><i class='fas fa-folder-open'></i>&nbsp;".__('Move to', 'wpcloudplugins').'</a></li>';
        }

        // Copy
        if ($usercancopy) {
            $html .= "<li><a class='entry_action_copy' title='".__('Make a copy', 'wpcloudplugins')."'><i class='fas fa-clone'></i>&nbsp;".__('Make a copy', 'wpcloudplugins').'</a></li>';
        }

        // Delete
        if ($usercandelete && ($item->get_permission('candelete') || $item->get_permission('cantrash'))) {
            $html .= "<li><a class='entry_action_delete' title='".__('Delete', 'wpcloudplugins')."'><i class='fas fa-trash'></i>&nbsp;".__('Delete', 'wpcloudplugins').'</a></li>';
        }

        if ('' !== $html) {
            return "<div class='entry-info-button entry-action-menu-button' title='".__('More actions', 'wpcloudplugins')."' tabindex='0'><i class='fas fa-ellipsis-v'></i><div id='menu-".$item->get_id()."' class='entry-action-menu-button-content tippy-content-holder'><ul data-id='".$item->get_id()."' data-name='".$item->get_basename()."'>".$html."</ul></div></div>\n";
        }

        return $html;
    }

    public function renderNewFolder()
    {
        $return = '';

        if (
            false === $this->get_processor()->get_user()->can_add_folders()
            || true === $this->_search
            || '1' === $this->get_processor()->get_shortcode_option('show_breadcrumb')
            ) {
            return $return;
        }

        $icon_set = $this->get_processor()->get_setting('icon_set');

        $return .= "<div class='entry folder newfolder' data-mimetype='application/vnd.google-apps.folder'>\n";
        $return .= "<div class='entry_block'>\n";
        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $return .= "<a class='entry_link'><img class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$icon_set."icon_10_addfolder_xl128.png' /></a>";
        $return .= "</div></div></div>\n";

        $return .= "<div class='entry-info'>";
        $return .= "<div class='entry-info-name'>";
        $return .= "<a class='entry_link' title='".__('Add folder', 'wpcloudplugins')."'><div class='entry-name-view'>";
        $return .= '<span>'.__('Add folder', 'wpcloudplugins').'</span>';
        $return .= '</div></a>';
        $return .= "</div>\n";

        $return .= "</div>\n";
        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function createFilesArray()
    {
        $filesarray = [];

        $this->setParentFolder();

        //Add folders and files to filelist
        if (count($this->_folder['contents']) > 0) {
            foreach ($this->_folder['contents'] as $node) {
                // Check if entry is allowed
                if (!$this->get_processor()->_is_entry_authorized($node)) {
                    continue;
                }

                // Use the orginial entry if the file/folder is a shortcut
                if ($node->is_shortcut()) {
                    $original_node = $node->get_original_node();

                    if (empty($original_node)) {
                        // If the shortcut is pointing to an entry that doesn't longer exists
                        continue;
                    }

                    $original_entry = $original_node->get_entry();

                    if (empty($original_entry)) {
                        continue;
                    }

                    $original_entry->set_shortcut_details($node->get_entry()->get_shortcut_details());
                    $filesarray[] = $original_entry;
                } else {
                    $filesarray[] = $node->get_entry();
                }
            }

            $filesarray = $this->get_processor()->sort_filelist($filesarray);
        }

        // Add 'back to Previous folder' if needed
        if (isset($this->_folder['folder'])) {
            $folder = $this->_folder['folder']->get_entry();

            if ($this->_search || $folder->get_id() === $this->get_processor()->get_root_folder()) {
                return $filesarray;
            }

            // Get previous folder ID from Folder Path if possible//
            $folder_path = $this->get_processor()->get_folder_path();
            $parentid = end($folder_path);
            if (!empty($parentid)) {
                $parentfolder = $this->get_processor()->get_client()->get_folder($parentid);
                array_unshift($filesarray, $parentfolder['folder']->get_entry());

                return $filesarray;
            }

            // Otherwise, list the parents directly
            foreach ($this->_parentfolders as $parentfolder) {
                array_unshift($filesarray, $parentfolder);
            }
        }

        return $filesarray;
    }
}
