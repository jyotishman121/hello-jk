jQuery(document).ready(function ($) {
  'use strict';

  /* Tabs*/
  $('ul.useyourdrive-nav-tabs li:not(".disabled")').click(function () {
    if ($(this).hasClass('disabled')) {
      return false;
    }
    var tab_id = $(this).attr('data-tab');

    $('ul.useyourdrive-nav-tabs  li').removeClass('current');
    $('.useyourdrive-tab-panel').removeClass('current');

    $(this).addClass('current');
    $("#" + tab_id).addClass('current');

    var hash = location.hash.replace('#', '');
    location.hash = tab_id;
    window.scrollTo(0, 0);
    window.parent.scroll(0, 0);
  });

  if (location.hash && location.hash.indexOf('TB_inline') < 0) {
    jQuery("ul.useyourdrive-nav-tabs " + location.hash + "_tab").trigger('click');
  }

  /* Accordions */
  $(".useyourdrive-accordion").accordion({
    active: false,
    collapsible: true,
    header: ".useyourdrive-accordion-title",
    heightStyle: "content",
    classes: {
      "ui-accordion-header": "useyourdrive-accordion-top",
      "ui-accordion-header-collapsed": "useyourdrive-accordion-collapsed",
      "ui-accordion-content": "useyourdrive-accordion-content"
    },
    icons: {
      "header": "fas fa-angle-down",
      "activeHeader": "fas fa-angle-up"
    }
  });
  $('.useyourdrive-accordion .ui-accordion-header span').removeClass('ui-icon ui-accordion-header-icon');

  /* Permissions Tagify Input fields */
  $('.useyourdrive-tagify.useyourdrive-permissions-placeholders').each(function () {
    var tagify = new Tagify(this, {
      enforceWhitelist: true,
      editTags: false,
      dropdown: {
        enabled: 0,
        highlightFirst: true,
        maxItems: 20,
        position: "text"
      },
      whitelist: whitelist,
      templates: {
        tag: function (tagData) {
          return "<tag title='" + tagData.text + "' contenteditable='false' spellcheck='false' tabIndex='-1'  class=" + this.settings.classNames.tag + (tagData.class ? tagData.class : "") + "><x class='tagify__tag__removeBtn'></x><div><img onerror='this.style.visibility=\"hidden\"' src='" + tagData.img + "'><span class='tagify__tag-type'>" + tagData.type + "</span><span class='tagify__tag-text'>" + (typeof tagData.text !== 'undefined' ? tagData.text : tagData.value) + "</span></div></tag>"

        },
        dropdownItem: function (tagData) {
          return "<div class='tagify__dropdown__item'><img onerror='this.style.visibility=\"hidden\"' src='" + tagData.img + "'><span class='tagify__tag-type'>" + tagData.type + "</span><span>" + tagData.text + "</span></div>"

        }
      }
    });
  });

  /* Media Player selection Box */
  $('#UseyourDrive_mediaplayer_skin_selectionbox').ddslick({
    width: '598px',
    background: '#f4f4f4',
    onSelected: function (item) {
      $("#UseyourDrive_mediaplayer_skin").val($('#UseyourDrive_mediaplayer_skin_selectionbox').data('ddslick').selectedData.value);
    }
  });

  /* Set mode */
  var mode = $('body').attr('data-mode');

  $("input[name=mode]:radio").change(function () {

    $('.forfilebrowser, .forgallery, .foraudio, .forvideo, .forsearch').hide();
    $("#UseyourDrive_linkedfolders").trigger('change');

    $('#settings_upload_tab, #settings_advanced_tab, #settings_manipulation_tab, #settings_notifications_tab, #settings_layout_tab, #settings_sorting_tab, #settings_exclusions_tab').removeClass('disabled');
    $('.download-options').show();

    mode = $(this).val();
    switch (mode) {
      case 'files':
        $('.forfilebrowser').not('.hidden').show();
        break;

      case 'upload':
        $('.foruploadbox').not('.hidden').show();
        $('#settings_upload_tab, #settings_notifications_tab').removeClass('disabled');
        $('#settings_sorting_tab, #settings_advanced_tab, #settings_exclusions_tab').addClass('disabled');
        $('.download-options').hide();
        $('#UseyourDrive_upload').prop("checked", true).trigger('change');
        $('#UseyourDrive_notificationdownload, #UseyourDrive_notificationdeletion').closest('.option').hide();
        $('#UseyourDrive_singleaccount').prop("checked", true).trigger('change');
        break;

      case 'search':
        $('.forsearch').not('.hidden').show();
        $('#settings_upload_tab').addClass('disabled');
        $('#UseyourDrive_search_field').prop("checked", true).trigger('change');
        $('#UseyourDrive_singleaccount').prop("checked", true).trigger('change');
        break;

      case 'gallery':
        $('.forgallery').show();
        break;

      case 'audio':
        $('.foraudio').show();
        $('.root-folder').show();
        $('#settings_upload_tab, #settings_manipulation_tab, #settings_notifications_tab').addClass('disabled');
        $('#UseyourDrive_singleaccount').prop("checked", true).trigger('change');
        break;

      case 'video':
        $('.forvideo').show();
        $('.root-folder').show();
        $('#settings_upload_tab, #settings_manipulation_tab, #settings_notifications_tab').addClass('disabled');
        $('#UseyourDrive_singleaccount').prop("checked", true).trigger('change');
        break;
    }

    $("#UseyourDrive_breadcrumb, #UseyourDrive_mediapurchase, #UseyourDrive_search, #UseyourDrive_showfiles, #UseyourDrive_slideshow, #UseyourDrive_upload, #UseyourDrive_encryption, #UseyourDrive_upload_convert, #UseyourDrive_rename, #UseyourDrive_move, #UseyourDrive_copy, #UseyourDrive_edit, #UseyourDrive_editdescription, #UseyourDrive_delete, #UseyourDrive_createdocument, #UseyourDrive_addfolder, #UseyourDrive_showsharelink, #UseyourDrive_deeplink").trigger('change');
    $('input[name=UseyourDrive_file_layout]:radio:checked').trigger('change').prop('checked', true);
    $('#UseyourDrive_linkedfolders').trigger('change');
  });

  $("input[name=UseyourDrive_file_layout]:radio").change(function () {
    switch ($(this).val()) {
      case 'grid':
        $('.forlistonly').fadeOut();
        break;
      case 'list':
        $('.forlistonly').fadeIn();
        break;
    }
  });

  $('[data-div-toggle]').change(function () {
    var toggleelement = '.' + $(this).attr('data-div-toggle');

    if ($(this).is(":checkbox")) {
      if ($(this).is(":checked")) {
        $(toggleelement).fadeIn().removeClass('hidden');
      } else {
        $(toggleelement).fadeOut().addClass('hidden');
      }
    } else if ($(this).is("select")) {
      if ($(this).val() === $(this).attr('data-div-toggle-value')) {
        $(toggleelement).fadeIn().removeClass('hidden');
      } else {
        $(toggleelement).fadeOut().addClass('hidden');
      }
    }
  });

  $("#UseyourDrive_linkedfolders").change(function () {
    $('input[name=UseyourDrive_userfolders_method]:radio:checked').trigger('change').prop('checked', true);
  });

  $("input[name=UseyourDrive_userfolders_method]:radio").change(function () {
    var is_checked = $("#UseyourDrive_linkedfolders").is(":checked");

    $('.root-folder').show();
    switch ($(this).val()) {
      case 'manual':
        if (is_checked) {
          $('.root-folder').hide();
        }
        $('.option-userfolders_auto').hide().addClass('hidden');
        break;
      case 'auto':
        $('.root-folder').show();
        $('.option-userfolders_auto').show().removeClass('hidden');
        break;
    }
  });


  $("input[name=sort_field]:radio").change(function () {
    switch ($(this).val()) {
      case 'shuffle':
        $('.option-sort-field').hide();
        break;
      default:
        $('.option-sort-field').show();
        break;
    }
  });

  $('#get_raw_shortcode').click(showRawShortcode);
  $('#do_insert').click(function (event) {
    insertShortcode(event);
  });

  $('.useyourdrive .list-container').on('click', '.entry_media_shortcode', function (event) {
    event.stopPropagation();
    insertShortcodeAsMedia($(this).closest('.entry'));
  });


  /* Initialise from shortcode */
  $('input[name=mode]:radio:checked').trigger('change').prop('checked', true);

  function createShortcode() {
    var dir = $(".root-folder .UseyourDrive.files").attr('data-id'),
      account_id = $(".root-folder .UseyourDrive.files").attr('data-account-id'),
      singleaccount = $('#UseyourDrive_singleaccount').prop("checked"),
      custom_class = $('#UseyourDrive_class').val(),
      linkedfolders = $('#UseyourDrive_linkedfolders').prop("checked"),
      show_files = $('#UseyourDrive_showfiles').prop("checked"),
      max_files = $('#UseyourDrive_maxfiles').val(),
      show_folders = $('#UseyourDrive_showfolders').prop("checked"),
      show_filesize = $('#UseyourDrive_filesize').prop("checked"),
      show_filedate = $('#UseyourDrive_filedate').prop("checked"),
      filelayout = $("input[name=UseyourDrive_file_layout]:radio:checked").val(),
      show_ext = $('#UseyourDrive_showext').prop("checked"),
      candownloadzip = $('#UseyourDrive_candownloadzip').prop("checked"),
      canpopout = $('#UseyourDrive_canpopout').prop("checked"),
      lightbox_navigation = $('#UseyourDrive_lightboxnavigation').prop("checked"),
      showsharelink = $('#UseyourDrive_showsharelink').prop("checked"),
      showrefreshbutton = $('#UseyourDrive_showrefreshbutton').prop("checked"),
      show_breadcrumb = $('#UseyourDrive_breadcrumb').prop("checked"),
      breadcrumb_roottext = $('#UseyourDrive_roottext').val(),
      search = $('#UseyourDrive_search').prop("checked"),
      search_field = $('#UseyourDrive_search_field').prop("checked"),
      search_from = $('#UseyourDrive_searchfrom').prop("checked"),
      search_term = $('#UseyourDrive_searchterm').val(),
      previewinline = $('#UseyourDrive_previewinline').prop("checked"),
      allow_preview = $('#UseyourDrive_allow_preview').prop("checked"),
      include_ext = $('#UseyourDrive_include_ext').val(),
      include = $('#UseyourDrive_include').val(),
      exclude_ext = $('#UseyourDrive_exclude_ext').val(),
      exclude = $('#UseyourDrive_exclude').val(),
      sort_field = $("input[name=sort_field]:radio:checked").val(),
      sort_order = $("input[name=sort_order]:radio:checked").val(),
      slideshow = $('#UseyourDrive_slideshow').prop("checked"),
      pausetime = $('#UseyourDrive_pausetime').val(),
      maximages = $('#UseyourDrive_maximage').val(),
      show_filenames = $('#UseyourDrive_showfilenames').prop("checked"),
      show_descriptions_on_top = $('#UseyourDrive_showdescriptionsontop').prop("checked"),
      target_height = $('#UseyourDrive_targetHeight').val(),
      max_width = $('#UseyourDrive_max_width').val(),
      max_height = $('#UseyourDrive_max_height').val(),
      upload = $('#UseyourDrive_upload').prop("checked"),
      upload_folder = $('#UseyourDrive_upload_folder').prop("checked"),
      upload_auto_start = $('#UseyourDrive_upload_auto_start').prop("checked"),
      upload_ext = $('#UseyourDrive_upload_ext').val(),
      encryption = $('#UseyourDrive_encryption').prop("checked"),
      encryption_passphrase = $('#UseyourDrive_encryption_passphrase').val(),
      minfilesize = $('#UseyourDrive_minfilesize').val(),
      maxfilesize = $('#UseyourDrive_maxfilesize').val(),
      maxnumberofuploads = $('#UseyourDrive_maxnumberofuploads').val(),
      convert = $('#UseyourDrive_upload_convert').prop("checked"),
      convert_formats = readCheckBoxes("input[name='UseyourDrive_upload_convert_formats[]']"),
      overwrite = $('#UseyourDrive_overwrite').prop("checked"),
      edit = $('#UseyourDrive_edit').prop("checked"),
      rename = $('#UseyourDrive_rename').prop("checked"),
      move = $('#UseyourDrive_move').prop("checked"),
      copy = $('#UseyourDrive_copy').prop("checked"),
      editdescription = $('#UseyourDrive_editdescription').prop("checked"),
      can_delete = $('#UseyourDrive_delete').prop("checked"),
      can_addfolder = $('#UseyourDrive_addfolder').prop("checked"),
      can_createdocument = $('#UseyourDrive_createdocument').prop("checked"),
      deeplink = $('#UseyourDrive_deeplink').prop("checked"),
      deletetotrash = $('#UseyourDrive_deletetotrash').prop("checked"),
      notification_download = $('#UseyourDrive_notificationdownload').prop("checked"),
      notification_upload = $('#UseyourDrive_notificationupload').prop("checked"),
      notification_deletion = $('#UseyourDrive_notificationdeletion').prop("checked"),
      notification_emailaddress = $('#UseyourDrive_notification_email').val(),
      notification_skip_email_currentuser = $('#UseyourDrive_notification_skip_email_currentuser').prop("checked"),
      user_folders = $('#UseyourDrive_user_folders').prop("checked"),
      use_template_dir = $('#UseyourDrive_userfolders_template').prop("checked"),
      template_dir = $(".template-folder .UseyourDrive.files").attr('data-id'),
      view_role = readTagify("input[name='UseyourDrive_view_role']"),
      download_role = readTagify("input[name='UseyourDrive_download_role']"),
      edit_role = readTagify("input[name='UseyourDrive_edit_role']"),
      share_role = readTagify("input[name='UseyourDrive_share_role']"),
      upload_role = readTagify("input[name='UseyourDrive_upload_role']"),
      rename_files_role = readTagify("input[name='UseyourDrive_rename_files_role']"),
      rename_folders_role = readTagify("input[name='UseyourDrive_rename_folders_role']"),
      move_files_role = readTagify("input[name='UseyourDrive_move_files_role']"),
      move_folders_role = readTagify("input[name='UseyourDrive_move_folders_role']"),
      copy_files_role = readTagify("input[name='UseyourDrive_copy_files_role']"),
      //copy_folders_role = readTagify("input[name='UseyourDrive_copy_folders_role']"),
      editdescription_role = readTagify("input[name='UseyourDrive_editdescription_role']"),
      delete_files_role = readTagify("input[name='UseyourDrive_delete_files_role']"),
      delete_folders_role = readTagify("input[name='UseyourDrive_delete_folders_role']"),
      addfolder_role = readTagify("input[name='UseyourDrive_addfolder_role']"),
      createdocument_role = readTagify("input[name='UseyourDrive_createdocument_role']"),
      deeplink_role = readTagify("input[name='UseyourDrive_deeplink_role']"),
      view_user_folders_role = readTagify("input[name='UseyourDrive_view_user_folders_role']"),
      mediaplayer_skin = $('#UseyourDrive_mediaplayer_skin').val(),
      mediaplayer_skin_default = $('#UseyourDrive_mediaplayer_default').val(),
      media_buttons = readCheckBoxes("input[name='UseyourDrive_media_buttons[]']"),
      autoplay = $('#UseyourDrive_autoplay').prop("checked"),
      showplaylist = $('#UseyourDrive_showplaylist').prop("checked"),
      showplaylistonstart = $('#UseyourDrive_showplaylistonstart').prop("checked"),
      playlistinline = $('#UseyourDrive_showplaylistinline').prop("checked"),
      mediafiledate = $('#UseyourDrive_media_filedate').prop("checked"),
      playlistthumbnails = $('#UseyourDrive_playlistthumbnails').prop("checked"),
      linktomedia = $('#UseyourDrive_linktomedia').prop("checked"),
      mediapurchase = $('#UseyourDrive_mediapurchase').prop("checked"),
      linktoshop = $('#UseyourDrive_linktoshop').val(),
      ads = $('#UseyourDrive_media_ads').prop("checked"),
      ads_tag_url = $('#UseyourDrive_media_adstagurl').val(),
      ads_skipable = $('#UseyourDrive_media_ads_skipable').prop("checked"),
      ads_skipable_after = $('#UseyourDrive_media_ads_skipable_after').val();

    var mode = $("input[name=mode]:radio:checked").val();
    var data = '';

    if (UseyourDrive_vars.shortcodeRaw === '1') {
      data += '[raw]';
    }

    data += '[useyourdrive ';

    if (custom_class !== '') {
      data += 'class="' + custom_class + '" ';
    }

    if (singleaccount === false) {
      dir = '';
      account_id = '';
      linkedfolders = false;
      data += 'singleaccount="0" ';
    }

    if (typeof dir === 'undefined' && ($("input[name=UseyourDrive_userfolders_method]:radio:checked").val() !== 'manual') && singleaccount === true) {
      $('#settings_folder_tab a').trigger('click');
      return false;
    }

    if (dir !== '') {
      if (linkedfolders && $("input[name=UseyourDrive_userfolders_method]:radio:checked").val() === 'manual') {

      } else {
        data += 'dir="' + dir + '" ';
      }
    }

    if (account_id !== '') {
      if (linkedfolders && $("input[name=UseyourDrive_userfolders_method]:radio:checked").val() === 'manual') {

      } else {
        data += 'account="' + account_id + '" ';
      }
    }

    if (max_width !== '') {
      if (max_width.indexOf("px") !== -1 || max_width.indexOf("%") !== -1) {
        data += 'maxwidth="' + max_width + '" ';
      } else {
        data += 'maxwidth="' + parseInt(max_width) + '" ';
      }
    }

    if (max_height !== '') {
      if (max_height.indexOf("px") !== -1 || max_height.indexOf("%") !== -1) {
        data += 'maxheight="' + max_height + '" ';
      } else {
        data += 'maxheight="' + parseInt(max_height) + '" ';
      }
    }

    data += 'mode="' + $("input[name=mode]:radio:checked").val() + '" ';

    if (include_ext !== '') {
      data += 'includeext="' + include_ext + '" ';
    }

    if (include !== '') {
      data += 'include="' + include + '" ';
    }

    if (exclude_ext !== '') {
      data += 'excludeext="' + exclude_ext + '" ';
    }

    if (exclude !== '') {
      data += 'exclude="' + exclude + '" ';
    }

    if (view_role !== 'administrator|editor|author|contributor|subscriber|pending|guest') {
      data += 'viewrole="' + view_role + '" ';
    }

    if (sort_field !== 'name') {
      data += 'sortfield="' + sort_field + '" ';
    }

    if (sort_field !== 'shuffle' && sort_order !== 'asc') {
      data += 'sortorder="' + sort_order + '" ';
    }

    if (linkedfolders === true) {
      var method = $("input[name=UseyourDrive_userfolders_method]:radio:checked").val();
      data += 'userfolders="' + method + '" ';

      if (method === 'auto' && use_template_dir === true && template_dir !== '') {
        data += 'usertemplatedir="' + template_dir + '" ';
      }

      if (view_user_folders_role !== 'administrator') {
        data += 'viewuserfoldersrole="' + view_user_folders_role + '" ';
      }
    }

    if (mode === 'upload') {

      if ($("input[name=UseyourDrive_userfolders_method]:radio:checked").val() !== 'manual' && (dir === 'drive' || dir === "shared-with-me" || dir === "shared-drives")) {
        $('#settings_folder_tab a').trigger('click');
        $('.root-folder .selected-folder').css("color", "red");
        return false;
      }

      data += 'downloadrole="none" ';
    } else if (download_role !== 'administrator|editor|author|contributor|subscriber|pending|guest') {
      data += 'downloadrole="' + download_role + '" ';
    }

    switch (mode) {
      case 'audio':
      case 'video':
        if (mediaplayer_skin !== mediaplayer_skin_default) {
          data += 'mediaskin="' + mediaplayer_skin + '" ';
        }

        if (media_buttons !== 'prevtrack|playpause|nexttrack|volume|current|duration|fullscreen') {
          if (media_buttons == 'all') {
            media_buttons = 'prevtrack|playpause|nexttrack|volume|current|duration|skipback|jumpforward|speed|shuffle|loop|fullscreen';
          }

          data += 'mediabuttons="' + media_buttons + '" ';
        }

        if (autoplay === true) {
          data += 'autoplay="1" ';
        }

        if (showplaylist === false) {
          data += 'hideplaylist="1" ';
        } else {

          if (showplaylistonstart === false) {
            data += 'showplaylistonstart="0" ';
          }

          if (mode === 'video' && playlistinline === true) {
            data += 'playlistinline="1" ';
          }

          if (mediafiledate === false) {
            data += 'filedate="0" ';
          }

          if (playlistthumbnails === false) {
            data += 'playlistthumbnails="0" ';
          }

          if (linktomedia === true) {
            data += 'linktomedia="1" ';
          }

          if (mediapurchase === true && linktoshop !== '') {
            data += 'linktoshop="' + linktoshop + '" ';
          }
        }

        if (mode === 'video' && ads === true) {
          data += 'ads="1" ';

          if (ads_tag_url !== '') {
            data += 'ads_tag_url="' + ads_tag_url + '" ';
          }

          if (ads_skipable) {
            data += 'ads_skipable="1" ';
          } else if (ads_skipable_after !== '') {
            data += 'ads_skipable_after="' + ads_skipable_after + '" ';
          }
        }

        if (max_files !== '-1' && max_files !== '') {
          data += 'maxfiles="' + max_files + '" ';
        }

        break;

      case 'files':
      case 'gallery':
      case 'upload':
      case 'search':
        if (mode === 'gallery') {

          if (maximages !== '') {
            data += 'maximages="' + maximages + '" ';
          }

          if (show_filenames === true) {
            data += 'showfilenames="1" ';
          }

          if (show_descriptions_on_top === true) {
            data += 'showdescriptionsontop="1" ';
          }

          if (target_height !== '') {
            data += 'targetheight="' + target_height + '" ';
          }

          if (slideshow === true) {
            data += 'slideshow="1" ';
            if (pausetime !== '') {
              data += 'pausetime="' + pausetime + '" ';
            }
          }
        }

        if (mode === 'files' || mode === 'search') {

          if (show_filesize === false) {
            data += 'filesize="0" ';
          }

          if (show_filedate === false) {
            data += 'filedate="0" ';
          }

          if (filelayout === 'list') {
            data += 'filelayout="list" ';
          }

          if (show_ext === false) {
            data += 'showext="0" ';
          }

          if (allow_preview === false) {
            data += 'forcedownload="1" ';
          }

          if (edit === true) {
            data += 'edit="1" ';

            if (edit_role !== 'administrator|editor|author') {
              data += 'editrole="' + edit_role + '" ';
            }
          }

          if (canpopout === true) {
            data += 'canpopout="1" ';
          }
        }

        if (show_files === false) {
          data += 'showfiles="0" ';
        }
        if (show_folders === false) {
          data += 'showfolders="0" ';
        }

        if (max_files !== '-1' && max_files !== '') {
          data += 'maxfiles="' + max_files + '" ';
        }

        if (maxnumberofuploads !== '-1' && maxnumberofuploads !== '0' && maxnumberofuploads !== '') {
          data += 'maxnumberofuploads="' + maxnumberofuploads + '" ';
        }

        if (previewinline === false) {
          data += 'previewinline="0" ';
        }

        if (lightbox_navigation === false) {
          data += 'lightboxnavigation="0" ';
        }

        if (candownloadzip === true) {
          data += 'candownloadzip="1" ';
        }

        if (showsharelink === true) {
          data += 'showsharelink="1" ';

          if (share_role !== 'all') {
            data += 'sharerole="' + share_role + '" ';
          }

        }

        if (deeplink === true) {
          data += 'deeplink="1" ';

          if (deeplink_role !== 'all') {
            data += 'deeplinkrole="' + deeplink_role + '" ';
          }
        }

        if (showrefreshbutton === false) {
          data += 'showrefreshbutton="0" ';
        }

        if (search === false && mode !== 'search') {
          data += 'search="0" ';
        } else {
          if (search_field === true) {
            data += 'searchcontents="1" ';
          }

          if (search_from === true) {
            data += 'searchfrom="selectedroot" ';
          }

          if (search_term !== '' && singleaccount === true) {
            data += 'searchterm="' + search_term + '" ';
          }
        }

        if (show_breadcrumb === true) {
          if (breadcrumb_roottext !== '') {
            data += 'roottext="' + breadcrumb_roottext + '" ';
          }
        } else {
          data += 'showbreadcrumb="0" ';
        }

        if (notification_download === true || notification_upload === true || notification_deletion === true) {
          if (notification_emailaddress !== '') {
            data += 'notificationemail="' + notification_emailaddress + '" ';
          }

          if (notification_skip_email_currentuser === true) {
            data += 'notification_skipemailcurrentuser="1" ';
          }

        }

        if (notification_download === true) {
          data += 'notificationdownload="1" ';
        }

        if (upload === true) {

          data += 'upload="1" ';

          if (upload_folder === false) {
            data += 'upload_folder="0" ';
          }

          if (upload_auto_start === false || (mode === 'upload' && editdescription === true)) {
            data += 'upload_auto_start="0" ';
          } else {
            data += 'upload_auto_start="1" ';
          }

          if (upload_role !== 'administrator|editor|author|contributor|subscriber') {
            data += 'uploadrole="' + upload_role + '" ';
          }

          if (minfilesize !== '') {
            data += 'minfilesize="' + minfilesize + '" ';
          }

          if (maxfilesize !== '') {
            data += 'maxfilesize="' + maxfilesize + '" ';
          }
          if (convert === true) {
            data += 'convert="1" ';

            if ($("input[name='UseyourDrive_upload_convert_formats[]']").not(":checked").length > 0) {
              data += 'convertformats="' + convert_formats + '" ';
            }
          }
          if (encryption === true) {
            data += 'upload_encryption="1" upload_encryption_passphrase="' + encryption_passphrase + '" ';
          }

          if (upload_ext !== '') {
            data += 'uploadext="' + upload_ext + '" ';
          }

          if (overwrite === true) {
            data += 'overwrite="1" ';
          }

          if (notification_upload === true) {
            data += 'notificationupload="1" ';
          }

        }

        if (rename === true) {
          data += 'rename="1" ';

          if (rename_files_role !== 'administrator|editor') {
            data += 'renamefilesrole="' + rename_files_role + '" ';
          }
          if (rename_folders_role !== 'administrator|editor') {
            data += 'renamefoldersrole="' + rename_folders_role + '" ';
          }
        }

        if (move === true) {
          data += 'move="1" ';

          if (move_files_role !== 'administrator|editor') {
            data += 'movefilesrole="' + move_files_role + '" ';
          }
          if (move_folders_role !== 'administrator|editor') {
            data += 'movefoldersrole="' + move_folders_role + '" ';
          }
        }

        if (copy === true) {
          data += 'copy="1" ';

          if (copy_files_role !== 'administrator|editor') {
            data += 'copyfilesrole="' + copy_files_role + '" ';
          }
          // if (copy_folders_role !== 'administrator|editor') {
          //   data += 'copyfoldersrole="' + copy_folders_role + '" ';
          // }
        }

        if (editdescription === true) {
          data += 'editdescription="1" ';

          if (editdescription_role !== 'administrator|editor') {
            data += 'editdescriptionrole="' + editdescription_role + '" ';
          }
        }

        if (can_delete === true) {
          data += 'delete="1" ';

          if (delete_files_role !== 'administrator|editor') {
            data += 'deletefilesrole="' + delete_files_role + '" ';
          }

          if (delete_folders_role !== 'administrator|editor') {
            data += 'deletefoldersrole="' + delete_folders_role + '" ';
          }

          if (notification_deletion === true) {
            data += 'notificationdeletion="1" ';
          }

          if (deletetotrash === false) {
            data += 'deletetotrash="0" ';
          }
        }

        if (can_createdocument === true) {
          data += 'createdocument="1" ';

          if (createdocument_role !== 'administrator|editor') {
            data += 'createdocumentrole="' + createdocument_role + '" ';
          }
        }

        if (can_addfolder === true) {
          data += 'addfolder="1" ';

          if (addfolder_role !== 'administrator|editor') {
            data += 'addfolderrole="' + addfolder_role + '" ';
          }
        }

        break;
    }

    data += ']';

    if (UseyourDrive_vars.shortcodeRaw === '1') {
      data += '[/raw]';
    }

    return data;
  }

  function doCallback(value) {
    var callback = $('form').data('callback');
    window.parent[callback](value);
  }

  function insertShortcode(event) {
    var data = createShortcode();
    event.preventDefault();

    if (data !== false) {
      doCallback(data)
    }
  }

  function insertShortcodeAsMedia($entry_element) {

    $("#UseyourDrive_showplaylist").prop("checked", false);
    $("#UseyourDrive_include").val($entry_element.data('id'));
    var file_name = $entry_element.find('.entry_link:first').data('filename');

    var video_extensions = ['mp4', 'm4v', 'ogg', 'ogv', 'webmv'];
    var audio_extensions = ['mp3', 'm4a', 'oga', 'wav', 'webm'];

    video_extensions.forEach(function (extension) {
      if (file_name.indexOf('.' + extension) >= 0) {
        $("input#video").trigger('click');
        return false;
      }
    });

    audio_extensions.forEach(function (extension) {
      if (file_name.indexOf('.' + extension) >= 0) {
        $("input#audio").trigger('click');
        return false;
      }
    });

    var data = createShortcode();

    if (data !== false) {
      doCallback(data)
    }
  }

  function showRawShortcode() {
    /* Close any open modal windows */
    $('#useyourdrive-modal-action').remove();
    var shortcode = createShortcode();

    if (shortcode === false) {
      return false;
    }

    /* Build the Shortcode Dialog */
    var modalbuttons = '';
    modalbuttons += '<button class="simple-button blue useyourdrive-modal-copy-btn" type="button" title="' + UseyourDrive_vars.str_copy_to_clipboard_title + '" >' + UseyourDrive_vars.str_copy_to_clipboard_title + '</button>';
    var modalheader = $('<a tabindex="0" class="close-button" title="' + UseyourDrive_vars.str_close_title + '" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
    var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" style="word-break: break-word;"><strong>' + shortcode + '</strong></div>');
    var modalfooter = $('<div class="useyourdrive-modal-footer"><div class="useyourdrive-modal-buttons">' + modalbuttons + '</div></div>');
    var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal light"><div class="modal-dialog"><div class="modal-content"></div></div></div>');
    $('body').append(modaldialog);
    $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);

    /* Set the button actions */
    $('#useyourdrive-modal-action .useyourdrive-modal-copy-btn').off('click');
    $('#useyourdrive-modal-action .useyourdrive-modal-copy-btn').click(function () {

      var $temp = $("<input>");
      $("body").append($temp);
      $temp.val(shortcode).select();
      document.execCommand("copy");
      $temp.remove();

    });

    /* Open the Dialog and load the images inside it */
    var modal_action = new RModal(document.getElementById('useyourdrive-modal-action'), {
      bodyClass: 'rmodal-open',
      dialogOpenClass: 'animated slideInDown',
      dialogCloseClass: 'animated slideOutUp',
      escapeClose: true
    });
    document.addEventListener('keydown', function (ev) {
      modal_action.keydown(ev);
    }, false);
    modal_action.open();
    window.modal_action = modal_action;

    return false;
  }

  function readCheckBoxes(element) {
    var values = readArrCheckBoxes(element);

    if (values.length === 0) {
      return "none";
    }

    if (values.length === $(element).length) {
      return "all";
    }

    return values.join('|');
  }

  function readTagify(element) {
    var tags = $(element).val();

    if (tags.length === 0) {
      return "none";
    }

    var tags_id = [];

    $.each(JSON.parse(tags), function (idx, obj) {
      tags_id.push(obj.id)
    });

    if (tags_id.indexOf('none') > -1) {
      return 'none';
    }

    if (tags_id.indexOf('all') > -1) {
      return 'all';
    }

    return tags_id.join('|')
  }


  function readArrCheckBoxes(element) {
    var values = $(element + ":checked").map(function () {
      return this.value;
    }).get();
    return values;
  }
});