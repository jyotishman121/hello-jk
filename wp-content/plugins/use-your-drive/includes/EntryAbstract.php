<?php

namespace TheLion\UseyourDrive;

abstract class EntryAbstract
{
    public $id;
    public $drive_id;
    public $name;
    public $basename;
    public $path;
    public $parents;
    public $extension;
    public $mimetype;
    public $trashed;
    public $is_dir = false;
    public $size;
    public $description;
    public $last_edited;
    public $last_edited_str;
    public $created_time;
    public $created_time_str;
    public $preview_link;
    public $download_link;
    public $direct_download_link;
    public $export_links;
    public $save_as = [];
    public $can_preview_by_cloud = false;
    public $can_edit_by_cloud = false;
    public $permissions = [
        'canpreview' => false,
        'candelete' => false,
        'canadd' => false,
        'canrename' => false,
        'canmove' => false,
    ];
    public $has_own_thumbnail = false;
    public $thumbnail_icon = false;
    public $thumbnail_small = false;
    public $thumbnail_small_cropped = false;
    public $thumbnail_large = false;
    public $thumbnail_original;
    public $folder_thumbnails = [];
    public $icon;
    public $backup_icon;
    public $media;
    public $shortcut_details = [];
    public $additional_data = [];
    // Parent folder, only used for displaying the Previous Folder entry
    public $pf = false;

    /**
     * Folders that only have a structural function and cannot be used to perform any actions (e.g. delete/rename/zip)
     * My Drive and Team Folders are such folders.
     */
    public $_special_folder = false;

    public function __construct($api_entry = null)
    {
        if (null !== $api_entry) {
            $this->convert_api_entry($api_entry);
        }

        $this->backup_icon = $this->get_default_icon();
    }

    public function __toString()
    {
        return serialize($this);
    }

    abstract public function convert_api_entry($entry);

    public function to_array()
    {
        $entry = (array) $this;

        // Remove Unused data
        //unset($entry['id']);
        unset($entry['parents'], $entry['mimetype'], $entry['direct_download_link'], $entry['additional_data']);

        $entry['size'] = ($entry['size'] > 0) ? $entry['size'] : '';

        return $entry;
    }

    public function get_id()
    {
        return $this->id;
    }

    public function set_id($id)
    {
        return $this->id = $id;
    }

    public function get_drive_id()
    {
        return $this->drive_id;
    }

    public function set_drive_id($drive_id)
    {
        return $this->drive_id = $drive_id;
    }

    public function get_name()
    {
        return $this->name;
    }

    public function set_name($name)
    {
        return $this->name = $name;
    }

    public function get_basename()
    {
        return $this->basename;
    }

    public function set_basename($basename)
    {
        return $this->basename = $basename;
    }

    public function get_path()
    {
        return $this->path;
    }

    public function set_path($path)
    {
        return $this->path = $path;
    }

    public function get_parents()
    {
        return $this->parents;
    }

    public function set_parents($parents)
    {
        return $this->parents = $parents;
    }

    public function has_parents()
    {
        return !empty($this->parents);
    }

    public function get_extension()
    {
        return $this->extension;
    }

    public function set_extension($extension)
    {
        return $this->extension = $extension;
    }

    public function get_mimetype()
    {
        return $this->mimetype;
    }

    public function set_mimetype($mimetype)
    {
        return $this->mimetype = $mimetype;
    }

    public function get_is_dir()
    {
        return $this->is_dir;
    }

    public function is_dir()
    {
        return $this->is_dir;
    }

    public function is_file()
    {
        return !$this->is_dir;
    }

    public function set_is_dir($is_dir)
    {
        return $this->is_dir = (bool) $is_dir;
    }

    public function get_size()
    {
        return $this->size;
    }

    public function set_size($size)
    {
        return $this->size = (int) $size;
    }

    public function get_description()
    {
        return $this->description;
    }

    public function set_description($description)
    {
        return $this->description = $description;
    }

    public function get_created_time()
    {
        return $this->created_time;
    }

    public function get_created_time_str()
    {
        // Add datetime string for browser that doen't support toLocaleDateString
        $created_time = $this->get_created_time();
        if (empty($created_time)) {
            return '';
        }

        $localtime = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($created_time)));
        $this->created_time_str = date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($localtime));

        return $this->created_time_str;
    }

    public function set_created_time($created_time)
    {
        return $this->created_time = $created_time;
    }

    public function get_last_edited()
    {
        return $this->last_edited;
    }

    public function get_last_edited_str()
    {
        // Add datetime string for browser that doen't support toLocaleDateString
        $last_edited = $this->get_last_edited();
        if (empty($last_edited)) {
            return '';
        }

        $localtime = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($last_edited)));
        $this->last_edited_str = date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($localtime));

        return $this->last_edited_str;
    }

    public function set_last_edited($last_edited)
    {
        return $this->last_edited = $last_edited;
    }

    public function get_preview_link()
    {
        return $this->preview_link;
    }

    public function set_preview_link($preview_link)
    {
        return $this->preview_link = $preview_link;
    }

    public function get_download_link()
    {
        return $this->download_link;
    }

    public function set_download_link($download_link)
    {
        return $this->download_link = $download_link;
    }

    public function get_direct_download_link()
    {
        return $this->direct_download_link;
    }

    public function set_direct_download_link($direct_download_link)
    {
        return $this->direct_download_link = $direct_download_link;
    }

    public function get_export_link($mimetype)
    {
        if (!isset($this->export_links[$mimetype])) {
            return null;
        }

        return $this->export_links[$mimetype];
    }

    public function get_export_links()
    {
        return $this->export_links;
    }

    public function set_export_links($export_links)
    {
        return $this->export_links = $export_links;
    }

    public function get_save_as()
    {
        return $this->save_as;
    }

    public function set_save_as($save_as)
    {
        return $this->save_as = $save_as;
    }

    public function get_can_preview_by_cloud()
    {
        return $this->can_preview_by_cloud;
    }

    public function set_can_preview_by_cloud($can_preview_by_cloud)
    {
        return $this->can_preview_by_cloud = $can_preview_by_cloud;
    }

    public function get_can_edit_by_cloud()
    {
        return $this->can_edit_by_cloud;
    }

    public function set_can_edit_by_cloud($can_edit_by_cloud)
    {
        return $this->can_edit_by_cloud = $can_edit_by_cloud;
    }

    public function get_permission($permission)
    {
        if (!isset($this->permissions[$permission])) {
            return null;
        }

        return $this->permissions[$permission];
    }

    public function get_permissions()
    {
        return $this->permissions;
    }

    public function set_permissions($permissions)
    {
        return $this->permissions = $permissions;
    }

    public function set_permissions_by_key($key, $permissions)
    {
        return $this->permissions[$key] = $permissions;
    }

    public function has_own_thumbnail()
    {
        return $this->has_own_thumbnail;
    }

    public function set_has_own_thumbnail($v)
    {
        return $this->has_own_thumbnail = (bool) $v;
    }

    public function get_trashed()
    {
        return $this->trashed;
    }

    public function set_trashed($v)
    {
        return $this->trashed = (bool) $v;
    }

    public function get_thumbnail_icon()
    {
        return $this->thumbnail_icon;
    }

    public function set_thumbnail_icon($thumbnail_icon)
    {
        return $this->thumbnail_icon = $thumbnail_icon;
    }

    public function get_thumbnail_small()
    {
        return $this->thumbnail_small;
    }

    public function set_thumbnail_small($thumbnail_small)
    {
        return $this->thumbnail_small = $thumbnail_small;
    }

    public function get_thumbnail_small_cropped()
    {
        return $this->thumbnail_small_cropped;
    }

    public function set_thumbnail_small_cropped($thumbnail_small_cropped)
    {
        return $this->thumbnail_small_cropped = $thumbnail_small_cropped;
    }

    public function get_thumbnail_large()
    {
        return $this->thumbnail_large;
    }

    public function set_thumbnail_large($thumbnail_large)
    {
        return $this->thumbnail_large = $thumbnail_large;
    }

    public function get_thumbnail_original()
    {
        return $this->thumbnail_original;
    }

    public function set_thumbnail_original($thumbnail_original)
    {
        return $this->thumbnail_original = $thumbnail_original;
    }

    public function set_folder_thumbnails($folder_thumbnails)
    {
        return $this->folder_thumbnails = $folder_thumbnails;
    }

    public function get_folder_thumbnails()
    {
        return $this->folder_thumbnails;
    }

    public function get_icon()
    {
        if (empty($this->icon)) {
            return $this->get_default_thumbnail_icon();
        }

        return $this->icon;
    }

    public function set_icon($icon)
    {
        return $this->icon = $icon;
    }

    public function get_media($setting = null)
    {
        if (!empty($setting)) {
            if (isset($this->media[$setting])) {
                return $this->media[$setting];
            }

            return null;
        }

        return $this->media;
    }

    public function set_media($media)
    {
        return $this->media = $media;
    }

    public function get_additional_data()
    {
        return $this->additional_data;
    }

    public function set_additional_data($additional_data)
    {
        return $this->additional_data = $additional_data;
    }

    public function is_parent_folder()
    {
        return $this->pf;
    }

    public function set_parent_folder($value)
    {
        return $this->pf = (bool) $value;
    }

    public function get_default_icon()
    {
        return $this->get_default_thumbnail_icon();
    }

    public function get_default_thumbnail_icon()
    {
        return Helpers::get_default_thumbnail_icon($this->get_mimetype());
    }

    public function is_special_folder()
    {
        return false !== $this->_special_folder;
    }

    public function get_special_folder()
    {
        return $this->_special_folder;
    }

    public function set_special_folder($value)
    {
        $this->_special_folder = $value;
    }

    public function get_shortcut_details($key = null)
    {
        if (!empty($key)) {
            if (isset($this->shortcut_details[$key])) {
                return $this->shortcut_details[$key];
            }

            return null;
        }

        return $this->shortcut_details;
    }

    public function set_shortcut_details($shortcut_details)
    {
        return $this->shortcut_details = $shortcut_details;
    }

    public function is_shortcut()
    {
        return !empty($this->shortcut_details);
    }
}
