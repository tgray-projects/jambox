/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

/**
 * Swarm jQuery Plugins
 *     jQuery plugins were created to support the swarm js modules
 */

// textarea resize plugin
(function($) {
    var measure = function(element) {
        var previous, current;
        element  = $(element);
        if (!element.parent().length) {
            return false;
        }

        previous = element.data('size-data');
        current  = {
            width:  element.width(),
            height: element.height()
        };

        if (!previous || current.width !== previous.width || current.height !== previous.height) {
            element.trigger($.Event('textarea-resize'));
            element.data('size-data', current);
        }

        return !!current.height;
    };

    $.fn.textareaResize = function() {
        return this.each(function() {
            var $this = $(this), mousedown, mousemove, triggered;
            if ($this.data('size-data')) {
                return;
            }

            // store the current size so we know when it changes
            $this.data('size-data', {
                width:  $this.width(),
                height: $this.height()
            });

            // add mousedown listener for best case
            $this.on('mousedown', function() {
                // setInterval produces a smoother result than setTimeout
                mousedown = setInterval(function() {
                    var measured = measure($this);
                    if (measured === false) {
                        clearInterval(mousedown);
                    }
                }, 15);
                triggered = true;
            });

            // add mousemove listeners for browsers that
            // won't fire mousedown on the resize control
            if (swarm.has.nonStandardResizeControl()) {
                $this.on('mousemove', function() {
                    triggered = true;
                    if (mousemove) {
                        return;
                    }

                    mousemove = setTimeout(function() {
                        mousemove = false;
                        measure($this);
                    }, 200);
                });
            }

            // add mouseup cleanup
            $(document).on('mouseup', function() {
                clearInterval(mousedown);
                if (triggered) {
                    measure($this);
                }
                triggered = false;
            });
        });
    };
}(window.jQuery));

// version slider plugin
(function($) {
    // constructor for Slider class
    var Slider = function(element, options) {
        this.$element = $(element);
        this.options  = options;

        // create the slider
        this.getPlot();
        this.renderRevisionPoints();
        this.setMarkerMode(options.markerMode || this.markerMode);

        // create a special tooltip for the marker which updates
        // as it travels along the plot
        var slider = this;
        this.$element.tooltip({
            selector:  '.rev, .marker, .connector',
            container: 'body',
            html:      true,
            template:  this.tooltipTemplate,
            title:     function() {
                // show a diff label for connector tooltips
                if ($(this).hasClass('connector')) {
                    var markers  = slider.getMarker('both'),
                        rightRev = slider.getRevisionData(markers.right.data('target')),
                        leftRev  = slider.getRevisionData(markers.left.data('target'));
                    return slider.getDiffLabel(rightRev, leftRev);
                }

                var closest = $($.data(this, 'target'));
                return closest.attr('data-original-title') || closest.attr('title');
            }
        });

        // we only present an interactive slider if we have more than one revision
        if ((this.getRevisionData() || []).length <= 1) {
            return;
        }

        // attach listeners
        this.$element.on('mousedown.slider', $.proxy(this.mousedown, this));
        $(document).on('mouseup.slider', $.proxy(this.mouseup, this));
    };

    Slider.prototype = {
        constructor:       Slider,
        markerMode:        1, // represents the number of supported markers, 1 or 2 are supported

        connectorTemplate: '<div class="connector" data-original-title="" draggable="false">'
                         +     '<div class="connector-inner"></div>'
                         + '</div>',
        markerTemplate:    '<div class="marker" data-original-title="" draggable="false">'
                         +     '<div class="marker-dot border-box"></div>'
                         + '</div>',
        tooltipTemplate:   '<div class="tooltip slider-tooltip">'
                         +     '<div class="tooltip-arrow"></div>'
                         +     '<div class="tooltip-inner"></div>'
                         + '</div>',

        // represents the px dist from the left to the center of the rev node
        distToCenterPx:  10,

        // updates the marker mode, and refreshes the marker display
        setMarkerMode: function(mode) {
            this.previousRevision = null;
            this.removeModeVisuals();
            if (mode === 1) {
                this.markerMode = mode;
                this.moveMarker('.active', null, true);
            } else if (mode === 2) {
                this.markerMode = mode;
                this.$connector = $(this.connectorTemplate).insertAfter(this.getPlot());
                this.moveMarker('.active', 'right', true);
            }
            this.updateActive();
        },

        // returns the marker closest to the passed position
        _getClosestMarker: function(position) {
            if (this.markerMode === 1) {
                return this.getMarker();
            }

            var markers     = this.getMarker("both"),
                leftOffset  = Math.abs(position - markers.left.offset().left),
                rightOffset = Math.abs(position - markers.right.offset().left);
            return leftOffset < rightOffset ? markers.left : markers.right;
        },

        // given a position within the slider, this function will return the closest revision node
        _getClosestRevNode: function(position, marker, elementWidth) {
            // the first and last rev only have have section, otherwise all other
            // revs have two sections, one on either side of their node. These functions
            // determine which section the position falls into, and then matches that
            // section to a revision index
            var numSections  = (this.getRevisionData().length * 2) - 2,
                section      = Math.floor((position - this.distToCenterPx) / (elementWidth / numSections)),
                rev          = Math.round(section / 2);
            this.setClosestRevNode(this.$element.find('.rev')[rev], marker);

            return marker.data('target');
        },

        // keep track of the closest rev node, so everyone is not always trying to calculate it
        setClosestRevNode: function(node, marker) {
            var closestRevNode = marker.data('target');
            if (closestRevNode === node) {
                return;
            }

            // updates classes to allow for styling current closest node
            // but only remove the style if another marker doesn't also point to it
            if (this.markerMode === 1 || this.getMarker().not(marker[0]).data('target') !== closestRevNode) {
                $(closestRevNode).removeClass('closest');
            }
            $(node).addClass('closest');

            marker.data('target', node);
            this.$element.trigger('slider-closest-change', this);

            // updates the tooltip's position if we are being clicked
            if (this._mouseListen) {
                var tooltip = this.$element.data('tooltip');
                tooltip.leave({currentTarget: closestRevNode});

                // only show the new tooltip if it is not already showing
                var nodeTooltip = $.data(node, 'tooltip');
                if (this.markerMode === 2) {
                    nodeTooltip = this.$connector.data('tooltip');
                    if (nodeTooltip) {
                        nodeTooltip.tip().find('.tooltip-inner').html(nodeTooltip.getTitle());
                    }
                } else if (!nodeTooltip || !nodeTooltip.tip()[0].parentNode) {
                    tooltip.enter({currentTarget: node});
                }
            }
        },

        // called whenever a mouse event is happening on the slider, passing snap
        // as true will result in the marker snapping to the nearest revision
        onMarkerMouseMove: function(e, snap) {
            this.dragging = true;
            var bounds    = this.$element[0].getBoundingClientRect(),
                position  = e.pageX - (bounds.left + $(window).scrollLeft()) + this.markerOffset,
                marker    = this.closestMarker;

            // constrain the position within the slider
            position      = Math.min(Math.max(position, this.distToCenterPx), (bounds.width + this.distToCenterPx));

            // additional constraints when in 2 marker mode, in order for the
            // inner markers to not ever reach the outer revision nodes
            if (this.markerMode === 2) {
                var distance = bounds.width / (this.getRevisionData().length - 1);
                if (marker.data('side') === 'left') {
                    position = Math.min(position, bounds.width + this.distToCenterPx - distance);
                } else {
                    position = Math.max(position, this.distToCenterPx + distance);
                }
            }

            marker.css({left: (position - this.distToCenterPx) + 'px', right: ''});
            this._getClosestRevNode(position, marker, bounds.width);

            // update the trailing marker if we have two markers
            if (this.markerMode === 2) {
                this.updateTrailingMarker(this.getMarker().not(marker[0]), null, position - this.distToCenterPx, bounds.width);
                // move connector tooltip in a setTimeout to reduce impact to user drag
                setTimeout($.proxy(this.moveConnectorTooltip, this), 0);
            }

            this.$element.trigger('slider-move', this, position);

            // snap to the nearest revision
            if (snap) {
                this.moveMarker(marker.data('target'), marker);
            }
        },

        // tooltips are not designed to be moved while being shown,
        // so this function takes care of helping the tooltip update it's position
        moveConnectorTooltip: function() {
            var nodeTooltip = this.$connector.data('tooltip');

            // show the tooltip if it is not already showing
            if (!nodeTooltip || !nodeTooltip.tip()[0].parentNode) {
                this.$element.data('tooltip').enter({currentTarget: this.$connector[0]});
                return;
            }

            var tip          = nodeTooltip.tip(),
                pos          = nodeTooltip.getPosition(),
                actualWidth  = tip[0].offsetWidth,
                actualHeight = tip[0].offsetHeight;

            nodeTooltip.applyPlacement(
                {top: pos.top - actualHeight, left: pos.left + pos.width / 2 - actualWidth / 2}, 'top'
            );
        },

        // keeps the trailing marker within a certain distance of the marker your moving
        updateTrailingMarker: function(marker, trailingTarget, trailingPosition, elementWidth) {
            var trailRight = marker.data('side') === 'right',
                revisions  = this.getRevisionData();

            // find the position where we want to move the trailing marker to using the trailingTarget
            if (trailingTarget) {
                var target = trailRight ? trailingTarget.next('.rev') : trailingTarget.prev('.rev');
                // swap to the other side for the edges
                if (!target.length) {
                    target     = trailRight ? trailingTarget.prev('.rev') : trailingTarget.next('.rev');
                    trailRight = !trailRight;
                }

                this.setClosestRevNode(target[0], marker);
                var index = parseInt(target.data('rev-index'), 10);
                marker.css(this.getRevNodePosition(index));
                this.$connector.css({
                    'left' : ((100 / (revisions.length - 1)) * (trailRight ? index - 1 : index)) + "%",
                    'width': (100 / (revisions.length - 1)) + '%'
                });

                return;
            }

            // Set distance from the trailingPosition
            var distance = elementWidth / (revisions.length - 1),
                position = trailRight ? trailingPosition + distance : trailingPosition - distance;
            position     = Math.min(Math.max(position, 0), elementWidth);
            this._getClosestRevNode(position + this.distToCenterPx, marker, elementWidth);

            // position the marker using pixels if it belongs on the far right, otherwise use percentage
            if (position >= elementWidth) {
                marker.css({left: '', right: '-' + (this.distToCenterPx * 2) + 'px'});
            } else {
                marker.css({left: ((position * 100) / elementWidth) + '%', right: ''});
            }

            this.$connector.css({
                'left' : ((trailRight ? trailingPosition : position) * 100 / elementWidth) + '%',
                'width': (Math.abs(position - trailingPosition) * 100 / elementWidth) + '%'
            });
        },

        // move the marker to the same position as the passed target, and update the active node
        moveMarker: function(target, side, positionOnly) {
            target = this.$element.find(target);
            if (!target.length) {
                return;
            }

            // position the marker
            var marker = side instanceof $ ? side : this.getMarker(side),
                index  = parseInt(target.data('rev-index'), 10);
            marker.css(this.getRevNodePosition(index));

            // also update the trailing marker if we are in a 2 marker mode
            if (this.markerMode === 2) {
                this.updateTrailingMarker(this.getMarker().not(marker[0]), target);
            }

            // update the current cached data
            this.setClosestRevNode(target[0], marker);

            // set rev target as the active node if it not already
            var activeMarker = this.markerMode === 2 ? this.getMarker('right') : marker;
            if (!positionOnly && !$(activeMarker.data('target')).hasClass('active')) {
                this.updateActive();
                this.$element.trigger('slider-moved', this);
            }
        },

        // update the active marker classes and store the active version
        updateActive: function() {
            var marker           = this.markerMode === 2 ? this.getMarker('right') : this.getMarker(),
                revNode          = $(marker.data('target'));

            this.currentRevision = this.getRevisionData(revNode);

            // set a previousRevision if we are in a 2 marker mode
            if (this.markerMode === 2 && revNode.prev('.rev').length) {
                this.previousRevision = this.getRevisionData(revNode.prev('.rev'));
            }

            this.$element.find('.active').not(revNode).removeClass('active');
            revNode.addClass('active');
        },

        // render revision nodes onto the plot
        renderRevisionPoints: function() {
            this.$element.find('.rev').remove();
            var slider    = this,
                revisions = this.getRevisionData();

            $.each(revisions, function(index, value) {
                var position = slider.getRevNodePosition(index);
                    position = "left: " + position.left + '; right: ' + position.right + ';';

                // append a new revision node to the slider
                var cls = (value.selected ? "active" : "") + (value.pending ? "" : " committed"),
                    rev = $(
                          '<div class="rev manual-tooltip ' + cls + '" style="' + position + '">'
                        +     '<div class="rev-dot border-box"></div>'
                        + '</div>'
                    );
                rev.data('rev-index', index);
                rev.attr('title', slider.getRevisionLabel(value));
                rev.appendTo(slider.$element);
            });
        },

        // returns the percentage position of a node based on its index
        getRevNodePosition: function(index) {
            var revisions = this.getRevisionData();

            // calculate node position either from the right if it's the
            // last node, or as a percentage from the left
            return (index === revisions.length - 1)
                ? {right: "-" + (this.distToCenterPx * 2) + "px", left: ''}
                : {left: ((100 / (revisions.length - 1)) * index) + "%", right: ''};
        },

        // returns label text for revision tooltips
        getRevisionLabel: function(value) {
            return '#' + value.rev + " " + swarm.te('by') + " <b>" + value.user + "</b> "
                 + $.timeago.inWords(Date.now() - (value.time * 1000)) + "<br />"
                 + '<span class="muted">'
                 +   swarm.te(value.pending ? 'Shelved in %s' : 'Committed in %s', [value.change])
                 + '</span>';
        },

        // returns label text for tooltips when comparing two nodes
        getDiffLabel: function(value, against) {
            return '<div class="text-left">#'
                 +   value.rev
                 + ' <span class="muted">'
                 +   swarm.te(value.pending ? 'Shelved in %s' : 'Committed in %s', [value.change])
                 + '</span><br />'
                 + '#' + against.rev
                 + ' <span class="muted">'
                 +   swarm.te(against.pending ? 'Shelved in %s' : 'Committed in %s', [against.change])
                 + '</span></div>';
        },

        // returns revision data, if you pass a revNode
        // it will only return the data for that node
        getRevisionData: function(revNode) {
            return revNode ? this.options.data[$(revNode).data('rev-index')] : this.options.data;
        },

        getMarker: function(side) {
            // supports using a declared marker, otherwise creates a new marker
            this.$marker = this.$marker || this.$element.find('.marker');

            // add as many markers as we need for the mode we are in
            var i;
            for (i = 0; i < this.markerMode; i++) {
                if (!this.$marker[i]) {
                    this.$marker = this.$marker.add($(this.markerTemplate).insertAfter(this.getPlot()));
                }
            }

            // return unfiltered result if a side isn't specified, or we only have one
            if (!side || this.$marker.length === 1) {
                return this.$marker;
            }

            // sort the markers into left and right sides
            var domFirst = this.$marker.eq(0).offset().left,
                domLast  = this.$marker.eq(1).offset().left;
            var sides    = {
                left:  domFirst <  domLast ? this.$marker.eq(0) : this.$marker.eq(1),
                right: domFirst >= domLast ? this.$marker.eq(0) : this.$marker.eq(1)
            };

            // store each markers current position
            sides.left.data('side', 'left');
            sides.right.data('side', 'right');

            // return either both sides, or the specifically requested side
            return side === 'both' ? sides : sides[side];
        },

        // remove any dom attributes or elements that are specific to a markerMode
        // this acts as a reset when changing marker modes
        removeModeVisuals: function() {
            if (this.$marker) {
                this.$marker.remove();
                this.$marker = null;
            }
            if (this.$connector) {
                this.$connector.remove();
                this.$connector = null;
            }
            this.$element.find('.closest').removeClass('closest');
        },

        getPlot: function() {
            // supports using a declared plot, otherwise creates a new plot
            this.$plot = this.$plot || this.$element.find('.plot');
            if (!this.$plot.length) {
                this.$plot = $('<div class="plot" />').prependTo(this.$element);
            }

            return this.$plot;
        },

        mousedown: function(e) {
            // only handle primary button mouse events
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();

            var target  = $(e.target),
                tooltip = this.$element.data('tooltip');

            // normalize inners to positioned parent node
            if (target.is('.marker-dot, .rev-dot, .connector-inner')){
                target = target.parent();
            }

            this.markerOffset  = 0;
            this._mouseListen  = true;
            this._clickTarget  = target[0];
            this.closestMarker = target.hasClass('marker') ? target : this._getClosestMarker(e.pageX);

            this.getMarker().addClass('moving');

            // turn off the tooltip handling, we will move it ourselves with the marker
            this.$element.off('mouseenter.tooltip', '.rev, .marker, .connector');
            this.$element.off('mouseleave.tooltip', '.rev, .marker, .connector');

            // calculate the marker offset when in marker mode 2
            if (this.markerMode === 2) {
                var side            = this.closestMarker.data('side'),
                    closestPosition = this.closestMarker.offset().left;
                if ((side === 'left' && e.pageX > closestPosition)
                        || (side === 'right' && e.pageX < closestPosition + (this.distToCenterPx * 2))) {
                    this.markerOffset = closestPosition - e.pageX + this.distToCenterPx;
                } else {
                    this.markerOffset = (closestPosition - e.pageX > 0 ? -1 : 1)
                                      * (this.$element.width() / ((this.getRevisionData().length - 1) * 2));
                }
            }

            // if the target is a revision node, move our marker directly to the node
            // else move to the current mouse position
            if (target.hasClass('rev')) {
                this.markerOffset = 0;
                this.moveMarker(target[0], this.closestMarker, true);
            } else {
                // open a tooltip if we only have one marker, or if the target is not part of the marker connector
                if (this.markerMode === 1 || !target.hasClass('connector')) {
                    var tooltipTarget = this.markerMode === 2 ? this.$connector[0] : this.closestMarker.data('target');
                    tooltip.enter({currentTarget: tooltipTarget});
                }
                this.onMarkerMouseMove(e);
            }

            // listen for mouse movements
            $(document).on('mousemove.slider', $.proxy(this.onMarkerMouseMove, this));

            // remove the marker's tooltip a bit later to minimize the fliker effect
            // when we are transitioning from a marker tooltip to a revision tooltip
            setTimeout($.proxy(function() {
                if (this.closestMarker) {
                    tooltip.leave({currentTarget: this.closestMarker[0]});
                }
            }, this), 500);
        },

        mouseup: function(e) {
            // ignore mouseup while we are not listening
            if (!this._mouseListen) {
                return;
            }

            // stop listening for mouse movements
            $(document).off('mousemove.slider');
            this.getMarker().removeClass('moving');

            // snap to the closest revision
            if (this.dragging) {
                this.onMarkerMouseMove(e, true);
            } else {
                this.moveMarker(this._clickTarget, this.closestMarker);
            }

            this.closestMarker = null;
            this.markerOffset  = 0;
            this._mouseListen  = false;
            this._clickTarget  = null;
            this.dragging      = false;

            // restore tooltip control to the bootstrap plugin
            var tooltip = this.$element.data('tooltip');
            this.$element.on('mouseleave.tooltip', '.rev, .marker, .connector', $.proxy(tooltip.leave, tooltip));
            this.$element.on('mouseenter.tooltip', '.rev, .marker, .connector', $.proxy(tooltip.enter, tooltip));
            this.$element.find('.rev, .connector').trigger('mouseout');
        },

        disable: function() {
            this.$element.addClass('disabled');
            this.$element.append('<div class="overlay"></div>');
            this.$element.off('mousedown.slider');
            $(document).off('mouseup.slider');
        }
    };

    // build the jquery plugin for the versionSlider
    $.fn.versionSlider = function(option) {
        return this.each(function() {
            var $this  = $(this),
                slider = $this.data('versionSlider');
            if (!slider) {
                var options = $.extend({}, $this.data(), typeof option === 'object' && option);
                $this.data('versionSlider', new Slider(this, options));
            } else if (typeof option === 'object') {
                $.extend(slider.options, option);
                slider.renderRevisionPoints();
                slider.setMarkerMode(slider.options.markerMode || slider.markerMode);
            }
        });
    };

    $.fn.versionSlider.Constructor = Slider;
}(window.jQuery));

// multipicker which makes use of typehead to select items for rendering as buttons within the associated container
(function($) {
    // constructor for MultiPicker
    var MultiPicker = function(element, options) {
        this.$element = $(element);
        this.options  = $.extend({}, $.fn.multiPicker.defaults, options);
        this.init();
    };

    MultiPicker.prototype = {
        constructor: MultiPicker,
        itemTemplate:
                '<div class="multipicker-item" data-value="{{>value}}">'
            +       '<div class="pull-left">'
            +           '<div class="btn-group">'
            +               '<button type="button" class="btn btn-mini btn-info button-name" disabled>{{>value}}</button>'
            +               '<button type="button" class="btn btn-mini btn-info item-remove"'
            +                       'title="{{te:"Remove"}}" aria-label="{{te:"Remove"}}">'
            +                   '<i class="icon-remove icon-white"></i>'
            +               '</button>'
            +           '</div>'
            +           '{{if inputName}}<input type="hidden" name="{{>inputName}}[]" value="{{>value}}">{{/if}}'
            +       '</div>'
            +   '</div>',

        init: function() {
            // setup underlying typeahead
            this.$element.typeahead(this.options);
            this.typeahead = this.$element.data('typeahead');

            this.typeahead.select      = $.proxy(this.select, this);
            this.typeahead.highlighter = $.proxy(this.highlighter, this);
            this.typeahead.matcher     = $.proxy(this.matcher, this);

            // add extra classes to multipicker element and the items container to assist with styling
            this.$element.addClass('multipicker-input');
            this.getItemsContainer().addClass('multipicker-items-container clearfix');

            // prevent default enter action on the element
            this.$element.on('keypress', function(e) {
                if(e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });

            // render initially selected items
            $.each(this.options.selected, $.proxy(function(index, label){
                this.addItem(label);
            }, this));

            // initialize element's required state
            this.update();

            // disable the element if the multi-picker is requested disabled in options
            if (this.options.disabled) {
                this.disable();
            }
        },

        setSource: function(source) {
            this.typeahead.source = source;
        },

        highlighter: function(item) {
            // escape the item and the query
            item        = $('<span />').text(item).html();
            var query   = $('<span />').text(this.typeahead.query).html();

            // we highlight by bolding the parts of the item that match the query
            // this is directly taken from bootstraps highlighter method @todo update with bootstrap
            // the query.replace is escaping any characters that would impact the next regexp
            query = query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
            return item.replace(new RegExp('(' + query + ')', 'ig'), function ($1, match) {
                return '<strong>' + match + '</strong>';
            });
        },

        matcher: function(item) {
            // exclude items that are already selected
            return item.toLowerCase().indexOf(this.typeahead.query.toLowerCase()) !== -1
                && this.getSelected().indexOf(this.typeahead.updater(item)) === -1;
        },

        addItem: function(value) {
            // don't bother adding already selected item
            if (this.getSelected().indexOf(value) !== -1) {
                return false;
            }

            // render item as button and place it into the associated container
            var container = this.getItemsContainer();
            if (container.length) {
                var itemNode = this.createItem(value).appendTo(container);

                // wire-up remove listener
                var plugin = this;
                itemNode.find('.item-remove').on('click', function (e) {
                    e.stopPropagation();
                    $(this).tooltip('destroy');
                    $(this).closest('.multipicker-item').remove();
                    plugin.update();
                });
            }

            this.update();
        },

        createItem: function(value) {
            // call passed createItem function if available, otherwise use our default
            if (this.options.createItem) {
                return this.options.createItem.call(this, value);
            }

            return $($.templates(this.itemTemplate).render({
                value: value, inputName: this.options.inputName
            }));
        },

        select: function() {
            var value  = this.typeahead.$menu.find('.active').attr('data-value'),
                result = this.addItem(this.typeahead.updater(value));

            // clear the element if adding selected item was successful
            if (result !== false) {
                this.$element.val('');
            }

            return this.typeahead.hide();
        },

        getSelected: function() {
            var items = [];
            this.getItemsContainer().find('.multipicker-item').each(function(){
                items.push($(this).data('value'));
            });
            return items;
        },

        getItemsContainer: function() {
            return $(this.options.itemsContainer);
        },

        update: function() {
            this.updateRequired();
            if ($.isFunction(this.options.onUpdate)) {
                this.options.onUpdate.call(this);
            }
        },

        updateRequired: function() {
            if (this.options.required === null) {
                return;
            }

            var required = typeof this.options.required === 'function' ? this.options.required.call(this) : this.options.required,
                isEmpty  = this.getSelected().length === 0;
            this.$element.prop('required', required && isEmpty).trigger('change');
        },

        clear: function() {
            var container = this.getItemsContainer();
            container.find('[title]').tooltip('destroy');
            container.find('.multipicker-item').remove();
            this.update();
        },

        disable: function () {
            this.$element.prop('disabled', true);
        },

        enable: function () {
            this.$element.prop('disabled', false);
        }
    };

    // build the jquery plugin for the multiPicker
    $.fn.multiPicker = function(option) {
        return this.each(function() {
            var $this       = $(this),
                multiPicker = $this.data('multipicker');
            if (!multiPicker) {
                var options = $.extend({}, $this.data(), typeof option === 'object' && option);
                $this.data('multipicker', new MultiPicker(this, options));
            } else if (typeof option === 'string') {
                multiPicker[option]();
            }
        });
    };

    $.fn.multiPicker.defaults = {
        itemsContainer: null,   // element or selector for placing selected items
        inputName:      null,   // name for the input element for selected items
        onUpdate:       null,   // function called when selected items are updated
        required:       null,   // whether the associated input element is required
                                // value can be boolean, callback or null; if null, then
                                // 'required' property will not be modified by this plugin
        createItem:     null,   // optional function that will override the default button creation
        selected:       [],     // list of initially selected items
        source:         [],     // list of all items available to select or callback
        disabled:       false   // set to true to disable the multi-picker
    };

    $.fn.multiPicker.Constructor = MultiPicker;
}(window.jQuery));

// user-multipicker
(function($) {
    var UserMultiPicker = function(element, options) {
        this.$element = $(element);
        this.options  = $.extend({}, $.fn.userMultiPicker.defaults, options);

        // pull out passed in source so we can normalize
        // it before it gets set on typeahead
        var source = this.options.source || [];
        delete this.options.source;

        this.init();
        this.typeahead.updater = this.updater;
        this.setSource(source);

        // disable the element and load the users list
        this.disable();

        // if the input is required, then explicitly mark as invalid while loading the source
        // because the ':invalid' pseudo-class does not match on disabled elements
        if (this.$element.prop('required') && !this.$element.val()) {
            this.$element.addClass('invalid');
        }

        // load the source unless the user multi-picker is disabled via options
        if (!this.options.disabled) {
            $.fn.userMultiPicker.Promise().done($.proxy(this.setSource, this));
        }
    };

    UserMultiPicker.prototype = $.extend({}, $.fn.multiPicker.Constructor.prototype, {
        constructor: UserMultiPicker,

        matcher: function(item) {
            // ignore item if it is an ignored user
            var match = (this.options.excludeUsers || []).indexOf(this.typeahead.updater(item)) === -1;

            // use the multiPicker matcher if we haven't already determine this isn't a match
            return match && $.fn.multiPicker.Constructor.prototype.matcher.call(this, item);
        },

        setSource: function(source) {
            // convert data to array of items for the multiPicker
            var users = $.map(source, function (user) {
                return user.id + (user.fullName !== user.id ? ' (' + user.fullName + ')' : '');
            });

            this.typeahead.source = users;
            this.enable();
            this.$element.removeClass('invalid');
        },

        updater: function(item) {
            return item.split(' (')[0];
        }
    });

    // build the jquery plugin for the userMultiPicker
    $.fn.userMultiPicker = function(option) {
        return this.each(function() {
            var $this       = $(this),
                multiPicker = $this.data('user-multipicker');
            if (!multiPicker) {
                var options = $.extend({}, $this.data(), typeof option === 'object' && option);
                $this.data('user-multipicker', new UserMultiPicker(this, options));
            } else if (typeof option === 'string') {
                multiPicker[option]();
            }
        });
    };

    var usersXhr = null;
    $.fn.userMultiPicker.Constructor = UserMultiPicker;
    $.fn.userMultiPicker.defaults    = $.extend({}, $.fn.multiPicker.defaults, {
        excludeUsers: []    // list of userIds to exclude from the typeahead
    });
    $.fn.userMultiPicker.Promise     = function() {
        if (!usersXhr) {
            usersXhr = $.ajax('/users?fields[]=id&fields[]=fullName');
        }
        return usersXhr;
    };
}(window.jQuery));

(function($) {
    function buildParamObject(refObj, fullKey, value, traditional, add ) {
        var rbracketSplit = /([^\[\]\s]*)\[([^\[\]\s]*)\](.*)/i;

        // if item represents an array or object, we recursively build it
        // else the item is a simple property and we add it without recursion
        if (rbracketSplit.test(fullKey) && !traditional) {
            var split   = rbracketSplit.exec(fullKey),
                key     = split[1], nextKey = split[2], leftovers = split[3],
                isArray = !nextKey || $.isNumeric(nextKey);

            // create the current object if it does not exist
            var obj = refObj[key] = refObj[key] || (isArray ? [] : {});

            // if we are at the end of this recursive branch, add the value here
            // else recurse down some more
            if (isArray && !leftovers) {
                add(refObj, key, value);
            } else {
                buildParamObject(obj, nextKey + leftovers, value, traditional, add);
            }
        } else {
            // in traditional mode, simply having multiple values assigned indicates an array
            if (traditional && refObj[fullKey] !== undefined && !$.isArray(refObj[fullKey])) {
                refObj[fullKey] = [refObj[fullKey]];
            }
            add(refObj, fullKey, value);
        }
    }

    var parseValue = function(value) {
        switch(value) {
            case    "undefined": value = null; break;
            case    "null"     :
            case    ""         : value = null;      break;
            case    "true"     : value = true;      break;
            case    "false"    : value = false;     break;
            default            : value = $.isNumeric(value) ? +value : value;
        }

        return value;
    };

    // Deserialize a query string into a set of key/values
    $.deparam = function(query, parse, traditional) {
        var add = function(obj, key, value) {
            value = parse ? parseValue(value) : value || "";
            if ($.isArray(obj[key])) {
                obj[key].push(value);
            } else {
                obj[key] = value;
            }
        };

        // support the default traditional setting
        if (traditional === undefined) {
            traditional = $.ajaxSettings.traditional;
        }

        var items  = query.replace(/^\?/, '').replace(/\+/g, '%20').split('&'),
            values = {};

        var i, param, key, value;
        for (i = 0; i < items.length; i++) {
            param = items[i].split('=');
            key   = decodeURIComponent(param[0]);
            value = param.length > 1 ? decodeURIComponent(param[1]) : '';

            buildParamObject(values, key, value, traditional, add);
        }

        // Return the resulting deserialization
        return values;
    };
}(window.jQuery));

(function($) {
    // if within a couple of pixels of the bottom of the page consider user
    // scrolled to the bottom (we need to be a little forgiving for Chrome)
    $.isScrolledToBottom = function(viewport, content) {
        viewport = viewport || window;
        content  = content  || document;
        return $(viewport).scrollTop() + 2 >= $(content).height() - $(viewport).height();
    };
}(window.jQuery));