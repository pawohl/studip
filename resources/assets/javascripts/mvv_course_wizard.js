window.STUDIP.MVV = window.STUDIP.MVV || {};

STUDIP.MVV.CourseWizard = {
    /**
     * Fetches the children of a given lvgroup.
     * @param node the ID of the parent.
     * @param assignable is the given lvgroup assignable?
     * @param classtype
     * @returns {boolean}
     */
    getTreeChildren(node, assignable, classtype) {
        const target = $('.' + (assignable ? 'lvgroup-tree-' : 'lvgroup-tree-assign-') + node);
        if (!target.hasClass('tree-loaded')) {
            const params =
                'step=' +
                $('input[name="step"]').val() +
                '&method=getLVGroupTreeLevel' +
                '&parameter[]=' +
                $('#' + node).attr('id') +
                '&parameter[]=' +
                classtype;
            $.ajax($('#studyareas').data('ajax-url'), {
                data: params,
                beforeSend() {
                    target.children('ul').append(
                        $('<li class="tree-loading">').html(
                            $('<img>')
                                .attr('src', STUDIP.ASSETS_URL + 'images/loading-indicator.svg')
                                .css('width', '16')
                                .css('height', '16')
                        )
                    );
                },
                success(data) {
                    const items = $.parseJSON(data);
                    target.find('.tree-loading').remove();
                    if (items.length > 0) {
                        const list = target.children('ul');
                        for (let i = 0; i < items.length; i++) {
                            if (items[i].assignable || items[i].has_children) {
                                list.append(STUDIP.MVV.CourseWizard.createTreeNode(items[i], assignable));
                            }
                        }
                    }
                    target.addClass('tree-loaded');

                    const onode = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'open_lvg_nodes[]')
                        .attr('value', node);
                    $('#lvgroup-tree-open-nodes').append(onode);
                },
                error(xhr, status, error) {
                    alert(error);
                }
            });
        }
        if (!target.hasClass('tree-open')) {
            target.removeClass('tree-closed').addClass('tree-open');
        } else {
            target.removeClass('tree-open').addClass('tree-closed');
        }
        var checkbox = target.children('input[id="' + node + '"]');
        checkbox.prop('checked', !checkbox.prop('checked'));
        return false;
    },

    /**
     * Search the lvgruppen tree for a given term and show all matching groups.
     * @returns {boolean}
     */
    searchTree() {
        const searchterm = $('#lvgroup-tree-search').val();
        if (searchterm !== '') {
            $.ajax($('#studyareas').data('ajax-url'), {
                data: {
                    step: $('input[name="step"]').val(),
                    method: 'searchLVGroupTree',
                    'parameter[]': searchterm
                },
                method: 'POST',
                beforeSend() {
                    $('#lvgroup-tree-search-start')
                        .parent()
                        .append(
                            $('<img>')
                                .attr('src', STUDIP.ASSETS_URL + 'images/loading-indicator.svg')
                                .attr('id', 'lvgroup-tree-search-loading')
                                .css('width', '16')
                                .css('height', '16')
                        );
                },
                success(data) {
                    $('#lvgroup-tree-search-loading').remove();
                    const items = $.parseJSON(data);
                    if (items.length > 0) {
                        $('#lvgroup-tree-search-reset')
                            .removeClass('hidden-js');
                        $('#lvgsearchresults ul').empty();
                        $('#lvgsearchresults').show();
                        for (var i = 0; i < items.length; i++) {
                            const lvgroup_html = $(items[i].html_string);
                            if ($('#lvgroup-tree-assigned-' + items[i].id).length) {
                                lvgroup_html
                                    .find('button')
                                    .first()
                                    .css('visibility', 'hidden');
                            }
                            $('#lvgsearchresults ul').append(lvgroup_html);
                        }
                    } else {
                        alert($('#studyareas').data('no-search-result'));
                    }
                },
                error(xhr, status, error) {
                    $('#lvgroup-tree-search-loading').remove();
                    alert(error);
                }
            });
        }
        return false;
    },

    /**
     * Reset a search and empty the search result.
     * @returns {boolean}
     */
    resetSearch() {
        $('#lvgroup-tree-search-reset').addClass('hidden-js');
        $('#lvgroup-tree-search').val('');
        $('#lvgsearchresults ul').empty();
        $('#lvgsearchresults').hide();
        return false;
    },

    /**
     * Creates a tree node element from given data.
     * @param values values for the node
     * @param assignable is the given lvgroup assignable?
     * @returns {*|jQuery}
     */
    createTreeNode(values, assignable) {
        const item = $('<li>');

        // Node in lvgroups tree.
        if (assignable) {
            const mvv_ids = values.id.split('-');

            item.addClass('lvgroup-tree-' + values.id);
            const assign = $('<button>')
                .attr('type', 'button')
                .attr('name', 'assign[' + values.id + ']')
                .addClass('btn-icon btn-icon--add btn-icon--inline')
                .attr('onclick', "return STUDIP.MVV.CourseWizard.assignNode('" + values.id + "')");
            if (values.assignable) {
                item.append(assign);
                item.append(document.createTextNode(' '));
            }
            if (values.has_children) {
                const input = $('<input>')
                    .attr('type', 'checkbox')
                    .attr('id', values.id);
                const label = $('<label>')
                    .addClass('undecorated')
                    .attr('for', values.id)
                    .attr(
                        'onclick',
                        "return STUDIP.MVV.CourseWizard.getTreeChildren('" + values.id + "', true, '" + values.mvvclass + "')"
                    );
                // Build link for opening the current node.
                let link = $('div#studyareas').data('forward-url');
                if (link.indexOf('?') > -1) {
                    link += '&open_node=' + values.id;
                } else {
                    link += '?open_node=' + values.id;
                }
                const openLink = $('<a>').attr('href', link);
                openLink.html(values.name);
                label.append(openLink);
                item.append(input);
                item.append(label);
                if (values.has_children) {
                    item.append('<ul>');
                }
                if (values.assignable && $('#lvgroup-tree-assigned-' + mvv_ids[0]).length > 0) {
                    assign.css('display', 'none');
                }
            } else {
                if ($('#lvgroup-tree-assigned-' + mvv_ids[0]).length > 0) {
                    assign.css('display', 'none');
                }
                item.html(item.html() + values.name);
                item.addClass('tree-node');
            }
        }

        $(item).data('id', values.id);
        return item;
    },

    /**
     * Assign a given node to the course.
     * @param id lvgoup ID to assign
     * @returns {boolean}
     */
    assignNode(id) {
        const root = $('#lvgroup-tree-assigned-selected');
        const params = 'step=' + $('input[name="step"]').val() + '&method=getAncestorTree' + '&parameter[]=' + id;
        $.ajax($('#studyareas').data('ajax-url'), {
            data: params,
            beforeSend() {
                STUDIP.MVV.CourseWizard.loadingOverlay($('div#assigned ul.css-tree'));
            },
            success(data,) {
                $('#loading-overlay').remove();
                const items = $.parseJSON(data);

                const lvgid = id.split('-');
                if ($('#lvgroup-tree-assigned-' + lvgid).length === 0) {
                    const input = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'lvgroups[]')
                        .attr('value', items.id);
                    root.before(input);
                    root.append(items.html_string);
                }

                $("button[name*='assign[" + lvgid[0] + "']").each(function () {
                    $(this).hide();
                });
                $("svg[name*='assign[" + lvgid[0] + "']").each(function () {
                    $(this).hide();
                });
            },
            error: function (xhr, status, error) {
                alert(error);
            }
        });
        return false;
    },

    /**
     * Show some visible indicator that there is
     * AJAX work in progress.
     * @param parent
     */
    loadingOverlay(parent) {
        const pos = parent.offset();
        const div = $('<div>')
            .attr('id', 'loading-overlay')
            .addClass('ui-widget-overlay')
            .width($(parent).width())
            .height($(parent).height())
            .css({
                position: 'absolute',
                top: pos.top,
                left: pos.left
            });
        const loading = $('<img>')
            .attr('src', STUDIP.ASSETS_URL + 'images/loading-indicator.svg')
            .css({
                width: 32,
                height: 32,
                'margin-left': div.width() / 2 - 32,
                'margin-top': div.height() / 2 - 32
            });
        div.append(loading);
        parent.append(div);
    },

    /**
     * Show details of a node.
     * @param id lvgroup ID to unassign
     * @returns {boolean}
     */
    showDetails(id) {
        if ($('#lvgruppe_selection_detail_' + id).is(':visible')) {
            $('#lvgruppe_selection_detail_' + id).empty();
            $('#lvgruppe_selection_detail_' + id).hide();
        } else {
            $('#lvgruppe_selection_detail_' + id).empty();
            const params = 'step=' + $('input[name="step"]').val() + '&method=getLVGroupDetails' + '&parameter[]=' + id;
            $.ajax($('#assigned').data('ajax-url'), {
                data: params,
                beforeSend() {
                    STUDIP.MVV.CourseWizard.loadingOverlay($('div#assigned ul.css-tree'));
                },
                success(data) {
                    $('#loading-overlay').remove();
                    const items = $.parseJSON(data);
                    $('#lvgroup-tree-assigned-' + id + ' ul').append(items.html_string);
                },
                error(xhr, status, error) {
                    alert(error);
                }
            });
            $('#lvgruppe_selection_detail_' + id).show();
        }
        return false;
    },

    /**
     * Show details of a searchnode.
     * @param id lvgroup ID to unassign
     * @returns {boolean}
     */
    showSearchDetails(id) {
        if ($('#lvgruppe_search_' + id + ' ul').is(':visible')) {
            $('#lvgruppe_search_' + id + ' ul').remove();
        } else {
            var params = 'step=' + $('input[name="step"]').val() + '&method=getLVGroupDetails' + '&parameter[]=' + id;
            $.ajax($('#studyareas').data('ajax-url'), {
                data: params,
                beforeSend() {
                    STUDIP.MVV.CourseWizard.loadingOverlay($('div#lvgsearchresults ul.css-tree'));
                },
                success(data) {
                    $('#loading-overlay').remove();
                    var items = $.parseJSON(data);
                    $('#lvgruppe_search_' + id).append('<ul>' + items.html_string + '</ul>');
                },
                error(xhr, status, error) {
                    alert(error);
                }
            });
        }

        return false;
    },

    /**
     * Remove a node from the assigned ones.
     * @param id lvgroup ID to unassign
     * @returns {boolean}
     */
    removeLVGroup(id) {
        $('#lvgroup-tree-assigned-' + id).remove();
        $("button[name*='assign[" + id + "']").show();
        return false;
    }
};
