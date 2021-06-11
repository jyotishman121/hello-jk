<?php

namespace TheLion\UseyourDrive;

class Mediaplayer
{
    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

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

    public function get_media_list()
    {
        $this->_folder = $this->get_processor()->get_client()->get_folder();

        if ((false === $this->_folder)) {
            die();
        }

        $subfolders = $this->get_processor()->get_client()->get_entries_in_subfolders($this->_folder['folder']);
        $this->_folder['contents'] = array_merge($subfolders, $this->_folder['contents']);
        $this->mediaarray = $this->createMediaArray();

        if (count($this->mediaarray) > 0) {
            $response = json_encode($this->mediaarray);

            $cached_request = new CacheRequest($this->get_processor());
            $cached_request->add_cached_response($response);
            echo $response;
        }

        die();
    }

    public function setFolder($folder)
    {
        $this->_folder = $folder;
    }

    public function createMediaArray()
    {
        $covers = [];
        $captions = [];

        // Add covers and Captions
        if (count($this->_folder['contents']) > 0) {
            foreach ($this->_folder['contents'] as $key => $node) {
                $child = $node->get_entry();

                if (!isset($child->extension)) {
                    continue;
                }

                if (in_array(strtolower($child->extension), ['png', 'jpg', 'jpeg'])) {
                    // Add images to cover array
                    $covertitle = str_replace('.'.$child->get_extension(), '', $child->get_name());
                    $coverthumb = $child->get_thumbnail_small_cropped();
                    $covers[$covertitle] = $coverthumb;
                    unset($this->_folder['contents'][$key]);
                } elseif ('vtt' === strtolower($child->extension)) {
                    /**
                     * VTT files are supported for captions:.
                     *
                     * Filename: Videoname.Caption Label.Language.VTT
                     */
                    $caption_values = explode('.', $child->get_basename());

                    if (3 !== count($caption_values)) {
                        continue;
                    }

                    $video_name = $caption_values[0];

                    if (!isset($captions[$video_name])) {
                        $captions[$video_name] = [];
                    }

                    $captions[$video_name][] = [
                        'label' => $caption_values[1],
                        'language' => $caption_values[2],
                        'src' => USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-stream&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$child->get_id().'&dl=1&caption=1&listtoken='.$this->get_processor()->get_listtoken(),
                    ];

                    unset($this->_folder['contents'][$key]);
                }
            }
        }

        $files = [];

        //Create Filelist array
        if (count($this->_folder['contents']) > 0) {
            $foldername = $this->_folder['folder']->get_entry()->get_name();

            foreach ($this->_folder['contents'] as $node) {
                $child = $node->get_entry();

                if (false === $this->is_media_file($node)) {
                    continue;
                }

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
                    $child = $original_entry;
                }

                $basename = $child->get_basename();
                $extension = $child->get_extension();

                if (isset($covers[$basename])) {
                    $thumbnail = $covers[$basename];
                } elseif (isset($covers[$foldername])) {
                    $thumbnail = $covers[$foldername];
                } else {
                    $thumbnail = $child->get_thumbnail_small_cropped();
                }
                $thumbnailsmall = str_replace('=w500-h375-c-nu', '=h200', $thumbnail);
                $poster = str_replace('=w500-h375-c-nu', '=s1024', $thumbnail);

                $folder_str = dirname($node->get_path($this->_folder['folder']->get_id()));
                $folder_str = trim(str_replace('\\', '/', $folder_str), '/');
                $path = $folder_str.$basename;

                // combine same files with different extensions
                if (!isset($files[$path])) {
                    $source_url = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-stream&account_id={$this->get_processor()->get_current_account()->get_id()}&id=".$child->get_id().'&dl=1&listtoken='.$this->get_processor()->get_listtoken();
                    if (('Yes' !== $this->get_processor()->get_setting('google_analytics'))) {
                        $cached_source_url = get_transient('useyourdrive_stream_'.$child->get_id().'_'.$child->get_extension());
                        if (false !== $cached_source_url && false === filter_var($cached_source_url, FILTER_VALIDATE_URL)) {
                            $source_url = $cached_source_url;
                        }
                    }

                    $files[$path] = [
                        'title' => $basename,
                        'name' => $basename,
                        'artist' => $child->get_description(),
                        'is_dir' => false,
                        'folder' => $folder_str,
                        'poster' => $poster,
                        'thumb' => $thumbnailsmall,
                        'size' => $child->get_size(),
                        'last_edited' => $child->get_last_edited(),
                        'last_edited_str' => $child->get_last_edited_str(),
                        'download' => (('1' === $this->get_processor()->get_shortcode_option('linktomedia')) && $this->get_processor()->get_user()->can_download()) ? str_replace('useyourdrive-stream', 'useyourdrive-download', $source_url) : false,
                        'source' => $source_url,
                        'captions' => isset($captions[$basename]) ? $captions[$basename] : [],
                        'type' => Helpers::get_mimetype($extension),
                        'extension' => $extension,
                        'height' => $child->get_media('height'),
                        'width' => $child->get_media('width'),
                        'duration' => $child->get_media('duration') / 1000, //ms to sec,
                        'linktoshop' => ('' !== $this->get_processor()->get_shortcode_option('linktoshop')) ? $this->get_processor()->get_shortcode_option('linktoshop') : false,
                    ];
                }
            }

            $files = $this->get_processor()->sort_filelist($files);
        }

        if ('-1' !== $this->get_processor()->get_shortcode_option('max_files')) {
            $files = array_slice($files, 0, $this->get_processor()->get_shortcode_option('max_files'));
        }

        return array_values($files);
    }

    public function is_media_file(CacheNode $node)
    {
        $entry = $node->get_entry();

        if ($entry->is_dir()) {
            return false;
        }

        // Use the orginial entry if the file/folder is a shortcut
        if ($node->is_shortcut()) {
            $original_entry = $node->get_original_node()->get_entry();
            $original_entry->set_shortcut_details($node->get_entry()->get_shortcut_details());
            $entry = $original_entry;
        }

        $extension = $entry->get_extension();
        $mimetype = $entry->get_mimetype();

        if ('audio' === $this->get_processor()->get_shortcode_option('mode')) {
            $allowedextensions = ['mp3', 'm4a', 'ogg', 'oga', 'wav'];
            $allowedimimetypes = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-wav'];
        } else {
            $allowedextensions = ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'webm'];
            $allowedimimetypes = ['video/mp4', 'video/ogg', 'video/webm'];
        }

        if (!empty($extension) && in_array($extension, $allowedextensions)) {
            return true;
        }

        return in_array($mimetype, $allowedimimetypes);
    }
}
