/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

var checkMarkdown =  function() {
    if ($('body').hasClass('route-file') || $('body').hasClass('route-project-browse')) {
        if ($('div.view-md').length > 0) {
            var projectId = $('body').attr('data-project');
            var path = $('ul.breadcrumb').attr('data-path');
            var file = $('ul.breadcrumb li:last-child').text();
            var baseUrl;

            // massage path to get view url
            var result = path.replace("/files/", "/view/");
            var finalResult = result.replace(file, "");

            if (typeof projectId === 'undefined') {
                baseUrl = finalResult;
            } else {
                baseUrl = '/projects/' + projectId + finalResult;
            }

            $('.view-md').find('img').each(function () {
                var href = $(this).attr('src');
                // look for external references, if not, assume local
                if (href.indexOf('/files/') !== 0) {
                    $(this).attr('src', swarm.url(baseUrl + href));
                }
            });
        }
    }
}

$(document).ready( function() {
    // load readme files via ajax
    if ($('body').hasClass('route-project')) {
        var projectId = $('body').attr('data-project');

        // fetch current readme, if available
        $.ajax({
            url:         swarm.url('/project/readme/' + projectId),
            type:        "GET",
            global:      false,
            success: function(data) {
                if (data.readme) {
                    $('.project-readme').html(data.readme);
                    // fix images
                    $('.project-readme img').each(function() {
                        var href = $(this).attr('src');
                        // look for external references, if not, assume local
                        if (href.indexOf('/') !== 0) {
                            $(this).attr('src', swarm.url(data.baseUrl + href));
                        }
                    });
                }
            }
        });
    }

    checkMarkdown()

    // update toolbar
    if ($('body').hasClass('route-project')
        || $('body').hasClass('route-project-activity')
        || $('body').hasClass('route-project-reviews')
        || $('body').hasClass('route-project-browse')
        || $('body').hasClass('route-project-jobs')) {

        var projectId = $('body').attr('data-project');

        // add Activity link
        var activity = '<li';
        if ($('body').hasClass('route-project-activity')) {
            activity += ' class="active"';
        }
        activity += '><a class="activity-link" href="' + swarm.url('/projects/' + projectId + '/activity/') + '">Activity</a></li>';
        $('.project-navbar .navbar-inner .nav li a.overview-link').parent().after(activity);
    }


});

$('.tab-content').bind('contentchanged', function() {
    checkMarkdown();
    alert('woo');
});