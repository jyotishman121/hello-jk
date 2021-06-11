jQuery(document).ready(function ($) {
  'use strict';

  var $button = $('#do_link');

  $button.click(function (e) {
    e.stopPropagation();
  });

  $('li.link-item').click(function (e) {
    e.stopPropagation();
    var type = $(this).find('a').data('type');
    linkDoc(type)
  });

  tippy($button.get(0), {
    trigger: 'click',
    content: $button.next('.tippy-content-holder').get(0),
    maxWidth: 350,
    allowHTML: true,
    placement: 'top-end',
    moveTransition: 'transform 0.2s ease-out',
    interactive: true,
    theme: 'wpcloudplugins-light',
    onShow: function (instance) {
      $(instance.popper).find('.tippy-content-holder').removeClass('tippy-content-holder');
    },
  });

  function doCallback(value) {
    var callback = $('form').data('callback');
    window.parent[callback](value);
  }

  function linkDoc(type) {
    var listtoken = $(".UseyourDrive.files").attr('data-token'),
      lastpath = $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-path'),
      entries = readCheckBoxes(".UseyourDrive[data-token='" + listtoken + "'] input[name='selected-files[]']"),
      account_id = $(".UseyourDrive.files").attr('data-account-id');

    if (entries.length === 0) {
      doCallback('');
    }

    $.ajax({
      type: "POST",
      url: UseyourDrive_vars.ajax_url,
      data: {
        action: 'useyourdrive-create-link',
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

              if (type === 'download') {
                linkresult.link = 'https://drive.google.com/uc?export=download&id=' + linkresult.id
              }

              data += '<a class="UseyourDrive-directlink" href="' + linkresult.link + '" target="_blank">' + linkresult.name + '</a><br/>';
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