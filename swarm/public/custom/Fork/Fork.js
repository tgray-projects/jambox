// if we're on a project, refetch the branches so we can wire up forking
$(document).ready( function() {
if ($('body').hasClass('route-project')) {

    var projectId = $('body').attr('data-project');

    $.ajax({
        url:         swarm.url('/project/' + projectId + '/parent'),
        type:        "GET",
        global:      false,
        success: function(data) {
            if (data.parentId) {
                var parentUrl = swarm.url('/project/' + data.parentId);
                $('<div class="muted pad3">This project is forked from the '
                    + '<a href="' + parentUrl + '">' + data.parentName
                    + '</a> project.</div>').insertAfter($('.project-sidebar .body .description'));
            }
        }
    });
}

if ($('body').hasClass('route-project') && $('body').hasClass('can-add-project')) {
    var creatorId    = $($('.project-navbar .brand a')[0]).text();
    var userId       = $.parseJSON($('body').attr('data-user')).id;

    // can't fork our own projects, yet.
    if (creatorId == userId) {
        return;
    }

    var projectId = $('body').attr('data-project');

    var template = '<button href="{{:url}}" class="btn btn-small" '
        + 'onclick="swarm.workshop.fork(this); return false;" '
        + 'title="" '
        + 'data-original-title="Fork branch \'{{:name}}\' to your own project." '
        + '>'
        + '<i class="icon-random"></i></button>';

    $('.project-sidebar .branches ul li').each(function(index, obj) {
        obj = $(obj);
        var id = obj.attr('data-branch-id');
        var button = $.templates(template).render({
            url:        swarm.url('/projects/' + projectId + '/fork/' + id),
            name:       obj.attr('data-branch-name'),
        });
        obj.find('.btn-group').append(button);
    });
}
});