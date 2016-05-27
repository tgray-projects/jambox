/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

$.ProjectImage = function(options) {
    $.extend(this, $.ProjectImage.defaults, options);

    if (this.label == null && this.type !== null) {
        this.label = this.type.charAt(0).toUpperCase() + this.type.slice(1);
    }

    if (this.projectId !== null && this.type !== null) {
        this.fetchCurrent();
    }
};

$.ProjectImage.defaults = {
    projectId: null,
    type: null,
    label: null,
    target: null,
    hasFormControls: false,
    descriptions: {
        avatar:  'Used on the home page and shows on the upper left of your project\'s page.  (128px x 128px)',
        splash:  'Used on the home page and shows at the top of your project\'s page.  (1200px x 255px)',
        empty:   'No image set; drag and drop an image here to upload.',
        replace: 'Drag and drop a new image here to upload.'
    }
};

$.ProjectImage.prototype = {
    fetchCurrent: function() {
        $.ajax({
            url:     swarm.url('/projects/' + this.projectId + '/image/' + this.type),
            type:    "GET",
            global:  false,
            context: this,
            success: function(data) {
                this.imageUrl = swarm.url('/attachments/' + data.id);
                this.attachment = data.id;
                if (this.hasFormControls) {
                    this.setFormImage(data.id);
                } else if (this.target !== null) {
                    this.target.prepend('<img class="project-' + this.type + '" src="' + this.imageUrl + '"/>');
                }
            }
        });
    },

    createFormControls: function(target) {
        var input = '<div class="control-group">'
            + '<label class="control-label" for="project-' + this.type +'">' + this.label + ' Image Upload</label>'
            + '<div class="controls can-attach">'
            + '<div class="' + this.type + ' image-placeholder" data-upload-url="'
            + swarm.url('/attachments/add') + '">' + this.descriptions.empty + '</div>'
            + '<span class="dimensions muted"><small>' + this.descriptions[this.type] + '</small></span>'
            + '</div>'
            + '<input type="hidden" id="' + this.type + '" name="' + this.type + '" value=""/>'
            + '</div>';

        $(target).before(input);

        this.hasFormControls = true;
    },

    setFormImage: function(attachmentId) {
        var target = $('.' + this.type + '.image-placeholder');
        target.text('');
        target.append('<div class="project-image-caption">' + this.descriptions.replace + '</div>');
        target.append('<img src="' + swarm.url('/attachments/' + attachmentId) + '"/>');
        $('#' + this.type).val(attachmentId);
        target.removeClass('empty');
    },

    loadHandler: function(xhr) {
        xhr.addEventListener('load', $.proxy(function(e) {
            var response    = this.response = e.currentTarget;

            // try and parse the response
            try {
                response.json    = $.parseJSON(response.responseText);
                response.isValid = response.status === 200 && response.json.isValid;
            } catch(err) {
                response.isValid = false;
            }

            // if response was valid, update the size and add attachment to hidden attachments field
            if (response.isValid) {
                this.uploaded = this.file.size;
                this.projectImage.setFormImage(response.json.attachment.id);
            }

            this.updateProgress();
        }, this));
    }
};

$(document).ready( function() {
    if ($('body').hasClass('route-project')) {
        var projectId = $('body').attr('data-project');

        var projectAvatar = new $.ProjectImage({
            type:      'avatar',
            projectId: projectId,
            target:    $('.route-project .project-sidebar .profile-info')
        });

        var projectSplash = new $.ProjectImage({
            type:      'splash',
            projectId: projectId,
            target:    $('.route-project .project-splash')
        });
    }

    if ($('body').hasClass('route-add-project')
      || $('body').hasClass('route-edit-project')) {
        var projectId = $('body').attr('data-project');
        var target    = $('.control-group.control-group-owners');
        var avatar    = new $.ProjectImage({type: 'avatar', projectId: projectId});
        avatar.createFormControls(target);

        var splash = new $.ProjectImage({type: 'splash', projectId: projectId});
        splash.createFormControls(target);

        $(target).closest('form').attr('enctype', 'multipart/form-data');
        $(target).closest('form').data('max-size', 1048576);

        $('body').on('dragenter', function() {
            $('div.avatar.image-placeholder').dropZone({
                uploaderOptions: {
                    extraData: {
                        '_csrf': $('body').data('csrf'),
                        id:   projectId,
                        type: 'avatar'
                    },
                    projectImage: avatar,
                    attachLoadHandler: avatar.loadHandler
                }
            });

            $('div.splash.image-placeholder').dropZone({
                uploaderOptions: {
                    extraData: {
                        '_csrf': $('body').data('csrf'),
                        id:   projectId,
                        type: 'splash'
                    },
                    projectImage: splash,
                    attachLoadHandler: splash.loadHandler
                }
            });
        });
    }
});