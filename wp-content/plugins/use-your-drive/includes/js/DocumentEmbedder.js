jQuery(document).ready(function ($) {
  'use strict';

  $('#do_embed').click(insertDoc);

  function doCallback(value) {
    var callback = $('form').data('callback');
    window.parent[callback](value);
  }

  function insertDoc() {
    var listtoken = $(".UseyourDrive.files").attr('data-token'),
      lastpath = $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-path'),
      entries = readCheckBoxes(".UseyourDrive[data-token='" + listtoken + "'] input[name='selected-files[]']"),
      account_id = $(".UseyourDrive.files").attr('data-account-id');

    if (entries.length === 0) {
      doCallback('')
    }

    $.ajax({
      type: "POST",
      url: UseyourDrive_vars.ajax_url,
      data: {
        action: 'useyourdrive-embedded',
        account_id: account_id,
        listtoken: listtoken,
        lastpath: lastpath,
        entries: entries,
        _ajax_nonce: UseyourDrive_vars.createlink_nonce
      },
      beforeSend: function () {
        $(".UseyourDrive .loading").height($(".UseyourDrive .ajax-filelist").height());
        $(".UseyourDrive .loading").fadeTo(400, 0.8);
        $(".UseyourDrive .insert_links").attr('disabled', 'disabled');
      },
      complete: function () {
        $(".UseyourDrive .loading").fadeOut(400);
        $(".UseyourDrive .insert_links").removeAttr('disabled');
      },
      success: function (response) {
        if (response !== null) {
          if (response.links !== null && response.links.length > 0) {

            var data = '';

            $.each(response.links, function (key, linkresult) {
              if (linkresult.type === 'iframe') {
                data += '<iframe src="' + linkresult.embeddedlink + '" height="480" style="width:100%;" frameborder="0" scrolling="no" class="uyd-embedded" allowfullscreen></iframe>';
              } else if (linkresult.type === 'image') {
                data += '<img src="' + linkresult.embeddedlink + '"\>';
              }
            });

            doCallback(data)
          } else { }
        }
      },
      dataType: 'json'
    });
    return false;
  }

  function readCheckBoxes(element) {
    var values = $(element + ":checked").map(function () {
      return this.value;
    }).get();
    return values;
  }
});