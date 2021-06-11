<?php

namespace TheLion\UseyourDrive\Integrations;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class GravityPDF
{
    public function init()
    {
        if (false === class_exists('GFPDF_Core')) {
            return;
        }

        add_action('gfpdf_post_save_pdf', [$this, 'useyourdrive_post_save_pdf'], 10, 5);
        add_filter('gfpdf_form_settings_advanced', [$this, 'useyourdrive_add_pdf_setting'], 10, 1);
        add_filter('useyourdrive_gravitypdf_set_folder_id', [&$this, 'useyourdrive_gravify_pdf_use_upload_folder'], 10, 5);
    }

    /*
     * GravityPDF
     * Basic configuration in Form Settings -> PDF:
     *
     * Always Save PDF = YES
     * [GOOGLE  DRIVE] Export PDF = YES
     * [[GOOGLE  DRIVE] ID = ID where the PDFs need to be stored
     */

    public function useyourdrive_add_pdf_setting($fields)
    {
        $fields['useyourdrive_save_to_googledrive'] = [
            'id' => 'useyourdrive_save_to_googledrive',
            'name' => '[GOOGLE  DRIVE] Export PDF',
            'desc' => 'Save the created PDF to Google Drive',
            'type' => 'radio',
            'options' => [
                'Yes' => __('Yes'),
                'No' => __('No'),
            ],
            'std' => __('No'),
        ];

        global $UseyourDrive;

        $main_account = $UseyourDrive->get_accounts()->get_primary_account();

        $account_id = '';
        if (!empty($main_account)) {
            $account_id = $main_account->get_id();
        }

        $fields['useyourdrive_save_to_account_id'] = [
            'id' => 'useyourdrive_save_to_account_id',
            'name' => '[GOOGLE  DRIVE] Account ID',
            'desc' => 'Account ID where the PDFs need to be stored. E.g. <code>'.$account_id.'</code>',
            'type' => 'text',
            'std' => $main_account,
        ];

        $fields['useyourdrive_save_to_googledrive_id'] = [
            'id' => 'useyourdrive_save_to_googledrive_id',
            'name' => '[GOOGLE  DRIVE] Folder ID',
            'desc' => 'Folder ID where the PDFs need to be stored. E.g. <code>0AfuC9ad2CCWUk9PVB</code>',
            'type' => 'text',
            'std' => '',
        ];

        return $fields;
    }

    public function useyourdrive_post_save_pdf($pdf_path, $filename, $settings, $entry, $form)
    {
        global $UseyourDrive;
        $processor = $UseyourDrive->get_processor();

        if (!isset($settings['useyourdrive_save_to_googledrive']) || 'No' === $settings['useyourdrive_save_to_googledrive']) {
            return false;
        }

        $file = [
            'tmp_path' => $pdf_path,
            'type' => mime_content_type($pdf_path),
            'name' => $entry['id'].'-'.$filename,
        ];

        if (!isset($settings['useyourdrive_save_to_account_id'])) {
            // Fall back for older PDF configurations
            $settings['useyourdrive_save_to_account_id'] = $UseyourDrive->get_accounts()->get_primary_account()->get_id();
        }

        $account_id = apply_filters('useyourdrive_gravitypdf_set_account_id', $settings['useyourdrive_save_to_account_id'], $settings, $entry, $form, $processor);
        $folder_id = apply_filters('useyourdrive_gravitypdf_set_folder_id', $settings['useyourdrive_save_to_googledrive_id'], $settings, $entry, $form, $processor);

        return $this->useyourdrive_upload_gravify_pdf($file, $account_id, $folder_id);
    }

    public function useyourdrive_upload_gravify_pdf($file, $account_id, $folder_id)
    {
        global $UseyourDrive;
        $processor = $UseyourDrive->get_processor();

        $requested_account = $processor->get_accounts()->get_account_by_id($account_id);
        if (null !== $requested_account) {
            $processor->set_current_account($requested_account);
        } else {
            error_log(sprintf("[WP Cloud Plugin message]: Google Drive account (ID: %s) as it isn't linked with the plugin", $account_id));
            die();
        }

        // Write file
        $chunkSizeBytes = 20 * 320 * 1000; // Multiple of 320kb, the recommended fragment size is between 5-10 MB.

        $processor->get_app()->get_client()->setDefer(true);

        // Create new Google Drive File
        // Create new Google File
        $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
        $googledrive_file->setName($file['name']);
        $googledrive_file->setMimeType($file['type']);
        $googledrive_file->setParents([$folder_id]);

        try {
            $request = $processor->get_app()->get_drive()->files->create($googledrive_file, ['supportsAllDrives' => true]);
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Not uploaded to the cloud on line %s: %s',  __LINE__, $ex->getMessage()));

            return false;
        }

        // Create a media file upload to represent our upload process.
        $media = new \UYDGoogle_Http_MediaFileUpload(
            $processor->get_app()->get_client(),
            $request,
            null,
            null,
            true,
            $chunkSizeBytes
        );

        $filesize = filesize($file['tmp_path']);
        $media->setFileSize($filesize);

        try {
            $upload_status = false;
            $bytesup = 0;
            $handle = fopen($file['tmp_path'], 'rb');
            while (!$upload_status && !feof($handle)) {
                @set_time_limit(60);
                $chunk = fread($handle, $chunkSizeBytes);
                $upload_status = $media->nextChunk($chunk);
                $bytesup += $chunkSizeBytes;
            }

            fclose($handle);
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Not uploaded to the cloud on line %s: %s',  __LINE__, $ex->getMessage()));

            return false;
        }

        $processor->get_app()->get_client()->setDefer(false);
    }

    public function useyourdrive_gravify_pdf_use_upload_folder($folder_id, $settings, $entry, $form, $processor)
    {
        if ('%upload_folder%' !== $folder_id) {
            return $folder_id;
        }

        if (is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if ('useyourdrive' === $field->type) {
                    if (isset($entry[$field->id])) {
                        $uploadedfiles = json_decode($entry[$field->id]);

                        if ((null !== $uploadedfiles) && (count((array) $uploadedfiles) > 0)) {
                            $first_entry = reset($uploadedfiles);

                            if (isset($first_entry->account_id)) {
                                $requested_account = $processor->get_accounts()->get_account_by_id($first_entry->account_id);
                            } else {
                                $requested_account = $processor->get_accounts()->get_primary_account();
                            }

                            if (null !== $requested_account) {
                                $processor->set_current_account($requested_account);
                            } else {
                                error_log("[WP Cloud Plugin message]: Google Drive account (ID: %s) as it isn't linked with the plugin");

                                return $folder_id;
                            }

                            $cached_entry = $processor->get_client()->get_entry($first_entry->hash, false);

                            $parents = $cached_entry->get_parents();
                            $parent_folder = reset($parents);

                            return $parent_folder->get_id();
                        }
                    }
                }
            }
        }

        return $folder_id;
    }
}

 new GravityPDF();
