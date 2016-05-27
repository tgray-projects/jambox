/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

$(document).ready( function() {
    var listTargets = $('.projects-list');
    $.each(listTargets, function(index, target) {
        frontpage.populate(target);
    });

    $('.projects-list.carousel').carousel({interval: 5000});

    // remove Reviews
    if ($('.navbar-site .nav li a[href="/reviews/"]').length !== 0) {
        $('.navbar-site .nav li a[href="/reviews/"]').remove();
    }

    // remove Jobs
    if ($('.navbar-site .nav li a[href="/jobs/"]').length !== 0) {
        $('.navbar-site .nav li a[href="/jobs/"]').remove();
    }

    // remove history
    if ($('.navbar-site .nav li a[href="/changes/"]').length !== 0) {
        $('.navbar-site .nav li a[href="/changes/"]').remove();
    }

    // rename files to browse
    if ($('.navbar-site .nav li a[href="/files/"]').length !== 0) {
        $('.navbar-site .nav li a[href="/files/"]').text('Browse');
    }

    // add Explore
    if ($('.navbar-site .nav li a[href="/explore/"]').length == 0) {
        var exploreMenu = '<li';
        if ($('body').hasClass('route-explore')) {
          exploreMenu += ' class="active"';
        }
        exploreMenu += '><a href="/explore/">Projects</a></li>';
        $('.navbar-site .nav-collapse .nav li:first-child').after(exploreMenu);
    }

    $('#signup').click(function() {window.location=swarm.url('/signup/');});
    $('#setupProject').click(function() {window.location=swarm.url('/projects/add/');});
    $('#joinProject').click(function() {window.location=swarm.url('/explore/');});
});

 frontpage = {
    populate: function(target) {
        var target = $(target);
        target.addClass('loading');

        var url = '/frontpage/projects-list/' + target.attr('data-type') + '/count/';

        if (target.attr('data-count') && $.isNumeric(target.attr('data-count'))) {
             url += target.attr('data-count');
        } else {
            url += 'null';
        }
        if (target.attr('data-type') == 'user') {
            url += '/user/' + target.attr('data-user');
        }

        var functionName = target.attr('data-presentation');
        $.ajax(swarm.url(url)).done(function(data) { frontpage[functionName](data, target); });
    },

     grid: function(data, target) {
         var container = $('ul', target);
         container.empty();

         $.each(data.projectList, function(index, project) {
             var item = $.templates(
                 '<li>'
                     + '<div class="project-avatar">{{:avatar}}</div>'
                     + '<div class="project-title">{{:name}}</div>'
                     + '</li>'
             ).render(project);

            container.append(item);
         });

         if (data.projectList.length == 0) {
             container.append('<div>No projects available at this time.</div>');
         }

         target.removeClass('loading');
     },

    carousel: function(data, target) {
        var container = $('.carousel-inner', target);
        container.empty();

        $.each(data.projectList, function(index, project) {
            var template = '<div class="item">'
                +   '<div class="project-container splash-container">'
                +       '<div class="carousel-caption">'
                +           '<span>{{:name}}</span>'
                +           '<span>{{:description}}</span>'
                +       '</div>'
                +   '</div>'
                +   '<img class="project-splash" src="{{:splash}}"/>'
                + '</div>';
            if (project.splash == undefined) {
                project.splash = 'custom/Avatar/defaultsplash.png';
            }
            var item = $.templates(template).render(project);

            $('.carousel-inner', target).append(item);

            $('.indicators-wrap ol.carousel-indicators', target).append(
                '<li data-target="#' + target.attr('id') + '" data-slide-to="' + jQuery.trim(index) +'"></li>'
            );
        });

        if (data.projectList.length == 0) {
            container.append('<div>No projects available at this time.</div>');
        } else {
            $($('.carousel-inner .item', target)[0]).addClass('active');
            $($('.indicators-wrap ol.carousel-indicators li', target)[0]).addClass('active');
        }

        target.removeClass('loading');
    }
 };

frontpage.activity = {
    _loading: false,

    load: function(stream, reset, deficit) {
        if (frontpage.activity._loading) {
            if (!reset) {
                return;
            }

            frontpage.activity._loading.abort();
            frontpage.activity._loading = false;
        }

        // select by attr as the stream may contain special characters such as period
        // that cause issue when doing a simple class selection
        var table = $("[class~='stream-" + (stream || 'global') + "']");

        // if reset requested, clear table contents
        if (reset) {
            table.data('last-seen',   null);
            table.data('end-of-data', null);
            table.find('tbody').empty();
        }

        // if there are no more activity records, nothing else to do
        if (table.data('end-of-data')) {
            return;
        }

        // add extra row indicating that we are loading data
        // row is initially hidden, shown after 2s or as soon as we detect a 'deficit'
        table.find('tbody').append(
            '<tr class="loading muted hide">'
                + ' <td colspan="3">'
                + '  <span class="loading">Loading...</span>'
                + ' </td>'
                + '</tr>'
        );
        setTimeout(function(){
            table.find('tbody tr.loading').removeClass('hide').find('.loading').addClass('animate');
        }, deficit === undefined ? 2000 : 0);

        // tweak table for authenticated user
        var originalStream = stream,
            scope          = swarm.localStorage.get('activity.scope') || 'user',
            user           = swarm.user.getAuthenticatedUser(),
            isSwitchable   = table.is('.switchable') && user !== null,
            isPersonal     = isSwitchable && scope === 'user';

        // change stream to personal if in personal view
        if (isPersonal) {
            stream = 'personal-' + user.id;
            table.addClass('stream-' + stream);
        }

        // apply type filter
        // the data-type-filter trumps the filter buttons
        // if data-type-filter is set, the filter buttons are disabled
        var type  = table.data('type-filter');
        if (type === null) {
            type  = table.find('th .nav-pills li.active a');
            type  = type.length && type.attr('class').match(/type-([\w]+)/).pop();
        }

        // only load activity older than the last loaded row
        var last = table.data('last-seen');

        // prepare urls for activity stream
        var url  = '/activity' + (stream ? '/streams/' + encodeURIComponent(stream) : '');

        var max  = 6;
        frontpage.activity._loading = $.ajax({
            url:        swarm.url(url),
            data:       {max: max, after: last, type: type || null},
            dataType:   'json',
            cache:      false,
            success:    function(data){
                table.find('tbody tr.loading').remove();

                data = data.activity;

                // if we have no activity to show and there is no more on the server, let the user know
                if (!table.find('tbody tr').length && !data.length && table.data('end-of-data')) {
                    table.find('tbody').append($(
                        '<tr class="activity-info"><td><div class="alert border-box pad3">'
                            + 'No ' + (type ? 'matching ' : '') + 'activity.'
                            + '</div></td></tr>'
                    ));
                }

                var html;
                $.each(data, function(key, event){
                    event.rowClass  = (event.topic  ? 'has-topic ' : '')
                        + (event.type   ? 'activity-type-'   + event.type + ' ' : '')
                        + (event.action ? 'activity-action-' + event.action.toLowerCase().replace(/ /g, '-') : '');
                    html = $.templates(
                        '<tr id="{{>id}}" class="row-main {{>rowClass}}">'
                            +   '<td width=32 class="activity-avatar">{{:avatar}}</td>'
                            +   '<td class="activity-body">'
                            +     '{{if user}}'
                            +       '{{if userExists}}<a href="/users/{{urlc:user}}">{{/if}}'
                            +       '<strong>{{>user}}</strong>'
                            +       '{{if userExists}}</a>{{/if}} '
                            +     '{{/if}}'
                            +     '{{if behalfOf}}'
                            +     ' (on behalf of '
                            +       '{{if behalfOfExists}}<a href="/users/{{urlc:behalfOf}}">{{/if}}'
                            +       '<strong>{{>behalfOf}}</strong>'
                            +       '{{if behalfOfExists}}</a>{{/if}}'
                            +     ') '
                            +     '{{/if}}'
                            +     '{{>action}} '
                            +     '{{if url}}<a href="{{:url}}">{{/if}}{{>target}}{{if url}}</a>{{/if}}'
                            +     '{{if preposition && projectList}} {{>preposition}} {{:projectList}}{{/if}}'
                            +   '</td>'
                            +   '<td class="color-stripe"></td>'
                            + '</tr>'
                            + '<tr class="row-spacing">'
                            +   '<td colspan="3"></td>'
                            + '</tr>'
                    ).render(event);

                    var row = $(html);
                    table.find('tbody').append(row);

                    // truncate the description
                    row.find('p.description').expander();

                    // convert times to time-ago
                    row.find('.timeago').timeago();
                });
            }
        });
    }
};
