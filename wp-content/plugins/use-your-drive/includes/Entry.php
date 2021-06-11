<?php

namespace TheLion\UseyourDrive;

class Entry extends EntryAbstract
{
    public function convert_api_entry($api_entry)
    {
        if (
                !$api_entry instanceof \UYDGoogle_Service_Drive_DriveFile
        ) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Google response is not a valid Entry.'));

            exit();
        }

        // @var $api_entry \UYDGoogle_Service_Drive_DriveFile

        // Normal Meta Data
        $this->set_id($api_entry->getId());
        $drive_id = (null !== $api_entry->getDriveId()) ? $api_entry->getDriveId() : 'mydrive';
        $this->set_drive_id($drive_id);
        $this->set_name($api_entry->getName());
        $this->set_extension(strtolower($api_entry->getFileExtension()));

        // Shortcuts don't provide extension information
        if ('application/vnd.google-apps.shortcut' === $api_entry->getMimeType()) {
            $pathinfo = Helpers::get_pathinfo($this->get_name());
            if (isset($pathinfo['extension'])) {
                $this->set_extension($pathinfo['extension']);
            }
        }

        $this->set_mimetype($api_entry->getMimeType());

        if (empty($this->extension)) {
            $this->set_basename($this->name);
        } else {
            $this->set_basename(str_replace('.'.$this->get_extension(), '', $api_entry->getName()));
        }

        $this->set_trashed($api_entry->getTrashed());
        $this->set_is_dir(('application/vnd.google-apps.folder' === $api_entry->getMimeType()));
        $this->set_size(($this->get_is_dir()) ? 0 : $api_entry->getSize());
        $this->set_description($api_entry->getDescription());
        $this->set_last_edited($api_entry->getModifiedTime());
        $this->set_created_time($api_entry->getCreatedTime());

        // Set Parents
        $this->set_parents($api_entry->getParents());

        // Set Shortcut
        $shortcut_details = $api_entry->getShortcutDetails();
        if (!empty($shortcut_details)) {
            $this->set_shortcut_details(
                [
                    'targetId' => $shortcut_details->getTargetId(),
                    'targetMimeType' => $shortcut_details->getTargetMimeType(), ]
            );
        }

        // Download & Export links
        $this->set_direct_download_link($api_entry->getWebContentLink());
        $this->set_save_as($this->create_save_as());
        $this->set_export_links($api_entry->getExportLinks());

        // Can file be viewed be previewed be google
        $preview_link = $api_entry->getWebViewLink();
        if (!empty($preview_link) && (!in_array($this->get_extension(), ['zip']) && $this->is_file())) {
            $this->set_can_preview_by_cloud(true);
        }
        $this->set_preview_link($preview_link);

        // Set Permission
        $capabilities = $api_entry->getCapabilities();

        $canpreview = false;
        $candownload = false;
        $canshare = false;
        $candelete = $cantrash = $api_entry->getOwnedByMe();
        $canadd = $api_entry->getOwnedByMe();
        $canmove = $api_entry->getOwnedByMe();
        $canrename = $api_entry->getOwnedByMe();
        $canchangecopyrequireswriterpermission = true;

        if (!empty($capabilities)) {
            $this->set_can_edit_by_cloud($capabilities->getCanEdit() && $this->edit_supported_by_cloud());
            $canadd = $capabilities->getCanEdit();
            $canrename = $capabilities->getCanRename();
            $canshare = $capabilities->getCanShare();
            $candelete = $capabilities->getCanDelete();
            $cantrash = $capabilities->getCanTrash();
            $canmove = $capabilities->getCanMoveItemWithinDrive();
            $canchangecopyrequireswriterpermission = $capabilities->getCanChangeCopyRequiresWriterPermission();
        }

        // Download permissions are a little bit tricky
        $users = [];
        $api_permissions = $api_entry->getPermissions();
        if (count($api_permissions) > 0) {
            foreach ($api_permissions as $permission) {
                $users[$permission->getId()] = ['type' => $permission->getType(), 'role' => $permission->getRole(), 'domain' => $permission->getDomain()];
            }
        }

        $candownload = true;
        $canpreview = true;

        // Set the permissions
        $permissions = [
            'canpreview' => $canpreview,
            'candownload' => $candownload,
            'candelete' => $candelete,
            'cantrash' => $cantrash,
            'canmove' => $canmove,
            'canadd' => $canadd,
            'canrename' => $canrename,
            'canshare' => $canshare,
            'copyRequiresWriterPermission' => $api_entry->getCopyRequiresWriterPermission(),
            'canChangeCopyRequiresWriterPermission' => $canchangecopyrequireswriterpermission,
            'users' => $users,
        ];

        $this->set_permissions($permissions);

        // Icon
        $icon = $api_entry->getIconLink();
        $this->set_icon(str_replace(['/16/', '+shared'], ['/64/', ''], $icon));

        // Thumbnail
        $this->set_thumbnails($api_entry->getThumbnailLink());

        // If entry has media data available set it here
        $mediadata = [];
        $imagemetadata = $api_entry->getImageMediaMetadata();
        $videometadata = $api_entry->getVideoMediaMetadata();
        if (!empty($imagemetadata)) {
            if (empty($imagemetadata->rotation) || 0 === $imagemetadata->getRotation() || 2 === $imagemetadata->getRotation()) {
                $mediadata['width'] = $imagemetadata->getWidth();
                $mediadata['height'] = $imagemetadata->getHeight();
            } else {
                $mediadata['width'] = $imagemetadata->getHeight();
                $mediadata['height'] = $imagemetadata->getWidth();
            }

            if (!empty($imagemetadata->time)) {
                $dtime = \DateTime::createFromFormat('Y:m:d H:i:s', $imagemetadata->getTime(), new \DateTimeZone('UTC'));

                if ($dtime) {
                    $mediadata['time'] = $dtime->getTimestamp();
                }
            }
        } elseif (!empty($videometadata)) {
            $mediadata['width'] = $videometadata->getWidth();
            $mediadata['height'] = $videometadata->getHeight();
            $mediadata['duration'] = $videometadata->getDurationMillis();
        }

        $this->set_media($mediadata);

        // Add some data specific for Google Drive Service
        $additional_data = [
            //'can_viewers_copy_content' => $api_entry->getViewersCanCopyContent()
        ];

        $this->set_additional_data($additional_data);
    }

    public function set_thumbnails($thumbnail)
    {
        $icon = $this->get_icon();

        if (empty($thumbnail)) {
            $this->set_thumbnail_icon($icon);
            $this->set_thumbnail_small(str_replace('/64/', '/256/', $icon));
            $this->set_thumbnail_small_cropped(str_replace('/64/', '/256/', $icon));
        } elseif (false !== strpos($thumbnail, 'google.com')) {
            // Thumbnails with feeds in URL give 404 without token?
            $thumbnail_small = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-thumbnail&s=small&id='.$this->get_id();
            $thumbnail_cropped = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-thumbnail&s=cropped&id='.$this->get_id();
            $thumbnail_large = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-thumbnail&s=large&c=0&id='.$this->get_id();
            $this->set_has_own_thumbnail(true);
            $this->set_thumbnail_icon($icon);
            $this->set_thumbnail_small($thumbnail_small);
            $this->set_thumbnail_small_cropped($thumbnail_cropped);
            $this->set_thumbnail_large($thumbnail_large);
            $this->set_thumbnail_original($thumbnail);
        } else {
            $this->set_has_own_thumbnail(true);
            $this->set_thumbnail_icon(str_replace('=s220', '=s16-c-nu', $thumbnail));
            $this->set_thumbnail_small(str_replace('=s220', '=w500-h375-nu', $thumbnail));
            $this->set_thumbnail_small_cropped(str_replace('=s220', '=w500-h375-c-nu', $thumbnail));
            $this->set_thumbnail_large(str_replace('=s220', '', $thumbnail));
            $this->set_thumbnail_original($thumbnail);
        }
    }

    public function get_thumbnail_with_size($thumbnailsize, $thumbnail_url = null)
    {
        if (empty($thumbnail_url)) {
            $thumbnail_url = $this->get_thumbnail_small();
        }

        return str_replace('=w500-h375-nu', '='.$thumbnailsize, $thumbnail_url);
    }

    public function create_save_as()
    {
        switch ($this->get_mimetype()) {
            case 'application/vnd.google-apps.document':
                $save_as = [
                    'MS Word document' => ['mimetype' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx', 'icon' => 'fa-file-word'], // First is default
                    'HTML' => ['mimetype' => 'text/html', 'extension' => 'html', 'icon' => 'fa-file-code'],
                    'Text' => ['mimetype' => 'text/plain', 'extension' => 'txt', 'icon' => 'fa-file-alt'],
                    'Open Office document' => ['mimetype' => 'application/vnd.oasis.opendocument.text', 'extension' => 'odt', 'icon' => 'fa-file-alt'],
                    'PDF' => ['mimetype' => 'application/pdf', 'extension' => 'pdf', 'icon' => 'fa-file-pdf'],
                    'ZIP' => ['mimetype' => 'application/zip', 'extension' => 'zip', 'icon' => 'fa-file-archive'],
                ];

                break;

            case 'application/vnd.google-apps.spreadsheet':
                $save_as = [
                    'MS Excel document' => ['mimetype' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension' => 'docx', 'icon' => 'fa-file-excel'],
                    'Open Office sheet' => ['mimetype' => 'application/x-vnd.oasis.opendocument.spreadsheet', 'extension' => 'ods', 'icon' => 'fa-file'],
                    'PDF' => ['mimetype' => 'application/pdf', 'extension' => 'pdf', 'icon' => 'fa-file-pdf'],
                    'CSV (first sheet only)' => ['mimetype' => 'text/csv', 'extension' => 'csv', 'icon' => 'fa-file-alt'],
                    'ZIP' => ['mimetype' => 'application/zip', 'extension' => 'zip', 'icon' => 'fa-file-archive'],
                ];

                break;

            case 'application/vnd.google-apps.drawing':
                $save_as = [
                    'JPEG' => ['mimetype' => 'image/jpeg', 'extension' => 'jpeg', 'icon' => 'fa-file-image'],
                    'PNG' => ['mimetype' => 'image/png', 'extension' => 'png', 'icon' => 'fa-file-image'],
                    'SVG' => ['mimetype' => 'image/svg+xml', 'extension' => 'svg', 'icon' => 'fa-file-code'],
                    'PDF' => ['mimetype' => 'application/pdf', 'extension' => 'pdf', 'icon' => 'fa-file-pdf'],
                ];

                break;

            case 'application/vnd.google-apps.presentation':
                $save_as = [
                    'MS PowerPoint document' => ['mimetype' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'extension' => 'pptx', 'icon' => 'fa-file-powerpoint'],
                    'PDF' => ['mimetype' => 'application/pdf', 'extension' => 'pdf', 'icon' => 'fa-file-pdf'],
                    'Text' => ['mimetype' => 'text/plain', 'extension' => 'txt', 'icon' => 'fa-file-alt'],
                ];

                break;

            case 'application/vnd.google-apps.script':
                $save_as = [
                    'JSON' => ['mimetype' => 'application/vnd.google-apps.script+json', 'extension' => 'json', 'icon' => 'fa-file-code'],
                ];

                break;

            case 'application/vnd.google-apps.form':
                $save_as = [
                    'ZIP' => ['mimetype' => 'application/zip', 'extension' => 'zip', 'icon' => 'fa-file-archive'],
                ];

                break;

            default:
                return [];
        }

        return $save_as;
    }

    public function edit_supported_by_cloud()
    {
        $is_supported = false;

        switch ($this->get_mimetype()) {
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/vnd.google-apps.document':
            case 'application/vnd.ms-excel':
            case 'application/vnd.ms-excel.sheet.macroenabled.12':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.google-apps.spreadsheet':
            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow':
            case 'application/vnd.google-apps.presentation':
            case 'application/vnd.google-apps.drawing':
                $is_supported = true;

                break;

            default:
                $is_supported = false;

                break;
        }

        return $is_supported;
    }
}
