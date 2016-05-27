/**
 * Created by tgray on 2014-05-19.
 */
$(document).ready( function() {
    // if viewing a project job, and we have permission, add the edit link
    if (($('body').hasClass('route-project-jobs') || $('body').hasClass('route-jobs'))
        && $('.job-wrapper .job-header')) {
        // if we're not authenticated, stop now and ensure reload on login
        if (!$('body').hasClass('authenticated')) {
            $('body').addClass('login-reload');
            return;
        }

        var jobId     = $('.job-wrapper .job-header h1').text();
        var projectId = $('body').attr('data-project');

        // no data-project info?  try grabbing it from the job description
        if (jobId && !projectId) {
            projectId = $('.job-wrapper .job-details .field-project a').text();
        }

        if(jobId && projectId) {
            // check permission
            $.ajax({
                url: swarm.url('/projects/' + projectId + '/job/' + jobId + '/check'),
                success: function (data) {
                    if (data.canEdit) {
                        var url = swarm.url('/projects/' + projectId + '/job/' + jobId + '/edit');
                        var footer = '<div class="popover-footer clearfix pad1"><a href="'+ url + '">Edit this job.</a></div>';
                        $('.job-header .job-info .change-description').after(footer);
                        $('.job-info .popover-footer a').attr('href', '');
                        $('.job-info .popover-footer a').on('click', function(e) {
                            e.preventDefault();
                            swarm.jobs.openEditDialog({title: 'Edit', url: url});
                        });
                    }
                }
            });
        }
    }

    // if we're on a project, refetch the branches so we can wire up extra functionality
    if ($('body').hasClass('route-project')) {
        var projectId = $('body').attr('data-project');

        var template =
              '<button href="{{:url}}" class="btn btn-small" '
            + 'onclick="swarm.browse.getArchive(this); return false;" '
            + 'title="" '
            + 'data-original-title="Download branch \'{{:name}}\' as a .zip file." '
            + '>'
            + '<i class="icon-briefcase"></i></button>';

        $('.project-sidebar .branches ul li').each(function(index, obj) {
            obj = $(obj);
            var id = obj.attr('data-branch-id');
            var button = $.templates(template).render({
                url:  swarm.url('/projects/' + projectId + '/archives/' + id + '.zip'),
                name: obj.attr('data-branch-name')
            });
            obj.find('.btn-group').append(button);
        });
    }
});

swarm.workshop = {
    fork: function(link) {
        link = $(link);
        link.toggleClass('active');

        // make a request to build fork in the background
        $.ajax({
            url:     link.attr('href'),
            data:    {background: true, format: 'json'},
            success: function(response) {
                if (response.id) {
                    window.location = swarm.url('/project/' + response.id);
                } else if (response.message) {
                    swarm.workshop.notification(response.message, 'error');
                } else {
                    swarm.workshop.notification('Unable to fork branch.', 'error');
                }
            },
            error: function(response, status, error) {
                swarm.workshop.notification('Unable to fork branch.', 'error');
            }
        });
    },

    notification: function(message, type, title) {
        // allow any of the supported bootstrap.js types.
        if (type != 'success' && type != 'error' && type != 'info') {
            type = 'info';
        }

        // create the new alert
        var template = '<div class="alert border-box alert-{{:type}}">'
            + '<button type="button" class="close" data-dismiss="alert">&times;</button>';
        if (title) {
            template += '<h4>{{>title}}</h4>';
        }
        template += '{{>message}}<div>';
        var alertHTML = $.templates(template).render({message: message, title: title, type: type});

        // wrap the alert in a div that the css can nicely position for us
        var notification = $('<div />', {'class': 'global-notification border-box'}).append(alertHTML).prependTo('body');

        // the alert will fire an event up the tree when
        // it is closed, remove our wrapper when it fires.
        notification.on('closed', function() {
            $(this).remove();
        });
    }
};

