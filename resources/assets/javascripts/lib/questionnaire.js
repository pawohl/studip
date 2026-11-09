import { $gettext } from './gettext';

const Questionnaire = {
    delayedQueue: [],
    delayedInterval: null,
    lastUpdate: null,
    filtered: {},
    initialize() {
        STUDIP.JSUpdater.register(
            'questionnaire',
            Questionnaire.updateQuestionnaireResults,
            Questionnaire.getParamsForPolling,
            15000
        );
    },
    getParamsForPolling: function() {
        var questionnaires = {
            questionnaire_ids: [],
            last_update: Questionnaire.lastUpdate,
            filtered: Questionnaire.filtered
        };
        Questionnaire.lastUpdate = Math.floor(Date.now() / 1000);
        jQuery('.questionnaire_results').each(function() {
            questionnaires.questionnaire_ids.push(jQuery(this).data('questionnaire_id'));
        });
        if (questionnaires.questionnaire_ids.length > 0) {
            return questionnaires;
        }
    },
    updateQuestionnaireResults: function(data) {
        for (var questionnaire_id in data) {
            if (data[questionnaire_id].html) {
                var new_view = jQuery(data[questionnaire_id].html);
                jQuery('.questionnaire_results.questionnaire_' + questionnaire_id).replaceWith(new_view);
                jQuery(document).trigger('dialog-open');
            }
        }
    },
    addFilter: function (questionnaire_id, question_id, answer) {
        Questionnaire.filtered[questionnaire_id] = {
            question_id: question_id,
            filterForAnswer: answer
        };
        $.ajax({
            url: STUDIP.URLHelper.getURL(STUDIP.ABSOLUTE_URI_STUDIP + 'dispatch.php/questionnaire/evaluate/' + questionnaire_id),
            data: {
                filtered: {
                    question_id: question_id,
                    filterForAnswer: answer
                }
            },
            success: Questionnaire.updateWidgetQuestionnaire,
            error: function () {
                window.alert('Cannot load page.');
            }
        });
    },
    removeFilter: function (questionnaire_id) {
        delete Questionnaire.filtered[questionnaire_id];
        $.ajax({
            url: STUDIP.URLHelper.getURL(STUDIP.ABSOLUTE_URI_STUDIP + 'dispatch.php/questionnaire/evaluate/' + questionnaire_id),
            success: Questionnaire.updateWidgetQuestionnaire,
            error: function () {
                window.alert('Cannot load page.');
            }
        });
    },
    updateOverviewQuestionnaire: function(data) {
        if (jQuery('#questionnaire_overview tr#questionnaire_' + data.questionnaire_id).length > 0) {
            jQuery('#questionnaire_overview tr#questionnaire_' + data.questionnaire_id).replaceWith(data.overview_html);
        } else {
            if (jQuery('#questionnaire_overview').length > 0) {
                jQuery(data.overview_html)
                    .hide()
                    .insertBefore('#questionnaire_overview > tbody > :first-child')
                    .delay(300)
                    .fadeIn();
                jQuery('#questionnaire_overview .noquestionnaires').remove();
            }
            if (data.message) {
                jQuery('.messagebox').hide();
                jQuery('#content').prepend(data.message);
            }
        }
        if (jQuery('.questionnaire_widget .widget_questionnaire_' + data.questionnaire_id).length > 0) {
            if (data.widget_html) {
                jQuery('.questionnaire_widget .widget_questionnaire_' + data.questionnaire_id).replaceWith(
                    data.widget_html
                );
            } else {
                jQuery('.questionnaire_widget .widget_questionnaire_' + data.questionnaire_id).remove();
            }
        } else {
            if (jQuery('.questionnaire_widget').length > 0 && data.widget_html) {
                jQuery('.ui-dialog-content').dialog('close');
                if (jQuery('.questionnaire_widget > article').length > 0) {
                    jQuery(data.widget_html)
                        .hide()
                        .insertBefore(
                            '.questionnaire_widget > article:first-of-type, .questionnaire_widget > section:first-of-type'
                        )
                        .delay(300)
                        .fadeIn();
                } else {
                    jQuery('.questionnaire_widget .noquestionnaires')
                        .replaceWith(data.widget_html)
                        .hide()
                        .delay(300)
                        .fadeIn();
                }
            } else {
                if (data.message) {
                    jQuery('.messagebox').hide();
                    jQuery('#content').prepend(data.message);
                    jQuery.scrollTo('#content', 400);
                }
            }
        }
        jQuery(document).trigger('dialog-open');
    },
    updateWidgetQuestionnaire: function(html) {
        //update the results of a questionnaire
        var questionnaire_id = jQuery(html).data('questionnaire_id');
        jQuery('.questionnaire_' + questionnaire_id).replaceWith(html);
        if (jQuery('.questionnaire_' + questionnaire_id).is('.ui-dialog .questionnaire_results')) {
            jQuery('.questionnaire_' + questionnaire_id + ' [data-dialog-button]').hide();
        }
    },
    beforeAnswer: function() {
        var form = jQuery(this).closest('form')[0];
        var questionnaire_id = jQuery(form)
            .closest('article')
            .data('questionnaire_id');
        let validated = true;

        //validation
        $(form).find("input, select, textarea").each(function () {
            if ($(this).is(":invalid")) {
                validated = false;
            }
        });

        $(form).find(".questionnaire_answer > article").each(function () {
            let question_type = $(this).data("question_type");
            if (typeof STUDIP.Questionnaire[question_type] !== "undefined"
                    && typeof STUDIP.Questionnaire[question_type].validator === "function") {
                if (!STUDIP.Questionnaire[question_type].validator.call(this)) {
                    validated = false;
                }
            }
        });

        if (!validated) {
            $(form).addClass("show_validation_hints");
            STUDIP.Report.warning($gettext("Noch nicht komplett ausgefüllt."), $gettext("Füllen Sie noch die rot markierten Stellen korrekt aus."));
            return false;
        }

        if (jQuery(form).is('.questionnaire_widget form')) {
            jQuery.ajax({
                url: STUDIP.ABSOLUTE_URI_STUDIP + 'dispatch.php/questionnaire/submit/' + questionnaire_id,
                data: new FormData(form),
                cache: false,
                processData: false,
                contentType: false,
                type: 'POST',
                success: function(output) {
                    jQuery(form).replaceWith(output);
                    jQuery(document).trigger('dialog-open');
                }
            });
            jQuery(form).css('opacity', '0.5');
            return false;
        } else {
            return true;
        }
    },
    Test: {
        updateCheckboxValues: function() {
            jQuery('.questionnaire_edit .question.test').each(function() {
                jQuery(this)
                    .find('.options > li')
                    .each(function(index, li) {
                        jQuery(li)
                            .find('input[type=checkbox]')
                            .val(index + 1);
                    });
            });
        }
    },
    Vote: {
        validator: function () {
            if ($(this).find(".mandatory").length > 0) {
                if ($(this).find(":selected, :checked").length === 0) {
                    $(this).find(".invalidation_notice").addClass("invalid");
                    return false;
                } else {
                    $(this).find(".invalidation_notice").removeClass("invalid");
                }
            }
            return true;
        }
    },
    LikertScale: {
        validator: function () {
            if ($(this).find(".mandatory").length > 0) {
                let invalid = false;
                $(this).find('table.answers tbody tr').each(function () {
                    if ($(this).find(':checked').length === 0) {
                        invalid = true;
                    }
                });
                if (invalid) {
                    $(this).find(".invalidation_notice").addClass("invalid");
                    return false;
                } else {
                    $(this).find(".invalidation_notice").removeClass("invalid");
                }
            }
            return true;
        }
    },
    RangeScale: {
        validator: function () {
            return Questionnaire.LikertScale.validator.call(this);
        }
    },

    async exportEvaluationAsPDF(results, button = null) {
        let initialButtonText = null;
        if (button) {
            button.setAttribute('disabled', 'disabled');
            initialButtonText = button.innerText;
            button.innerText = $gettext('Export läuft...');
        }

        const [html2canvas, jsPDF] = await Promise.all([
            import('html2canvas').then(m => m.default),
            import('jspdf').then(m => m.default),
        ]);

        const pdf = new jsPDF({
            orientation: 'portrait'
        });

        results.classList.add('print-view');

        const title = results.dataset.title;
        const splitTitle = pdf.splitTextToSize(title, 180);

        const formattedDate = new Intl.DateTimeFormat(String.locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',

            hour: 'numeric',
            minute: 'numeric'
        }).format(new Date());

        const canvasses = [];
        // This may seem awkward but since we cannot use parallel processing via
        // Promise.all, we need to wait for each render to finish before
        // continuing with the next one.
        // Since eslint doesn't like await in a loop, we need to use
        // Array.reduce().
        await Array.from(results.querySelectorAll('.question')).reduce(
            async (prev, element) => {
                await prev;

                element.querySelectorAll('svg.ct-chart-bar').forEach(svg => {
                    // Remove xmlns attribute from all children of the svg
                    svg.querySelectorAll('[xmlns]').forEach(node => {
                        node.removeAttribute('xmlns');
                    });

                    // Replace foreignObject with SVG text for html2canvas compatibility
                    svg.querySelectorAll('foreignObject').forEach(fo => {
                        const span = fo.querySelector('span');
                        if (!span) return;

                        const x = Number(fo.getAttribute('x'));
                        const y = Number(fo.getAttribute('y'));
                        const width = Number(fo.getAttribute('width'));
                        const height = Number(fo.getAttribute('height'));
                        const isVertical = span.classList.contains('ct-vertical');

                        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        if (isVertical) {
                            text.setAttribute('x', x + width);
                            text.setAttribute('y', y + height - 2);
                            text.setAttribute('text-anchor', 'end');
                        } else {
                            text.setAttribute('x', x + width / 2);
                            text.setAttribute('y', y + 14);
                            text.setAttribute('text-anchor', 'middle');
                        }
                        text.setAttribute('font-size', '12');
                        text.setAttribute('fill', 'currentColor');
                        text.textContent = span.textContent.trim();
                        fo.replaceWith(text);
                    });

                    // Set width and height as attribute, not as style
                    svg.setAttribute('width', svg.getBoundingClientRect().width);
                    svg.setAttribute('height', svg.getBoundingClientRect().height);
                    svg.style.width = null;
                    svg.style.height = null;
                });

                const canvas = await html2canvas(element, {
                    allowTaint: false,
                    foreignObjectRendering: false,
                    useCORS: true,
                    logging: false
                });

                canvasses.push(canvas);
            },
            Promise.resolve()
        );

        //then all renders are finished:
        let height_sum = 15;
        canvasses.forEach((canvas, index) => {
            let height = Math.floor(160 / canvas.width * canvas.height);
            if (height_sum + height > 240 && height < 240) {
                pdf.addPage();
                height_sum = 15;
            }
            pdf.addImage(
                canvas.toDataURL('image/png'),
                'JPEG',
                25,
                20 + height_sum,
                160,
                height,
                'image_' + index,
                'FAST',
            );
            height_sum += height + 10;
        })

        const pages = pdf.internal.getNumberOfPages();

        for (let i = 1; i <= pages; i++) {
            let pageSize = pdf.internal.pageSize;
            let pageHeight = pageSize.getHeight();
            pdf.setPage(i);
            pdf.setFontSize(16);
            pdf.text(splitTitle, 25, 20);
            pdf.setFontSize(8);
            pdf.text(
                String(formattedDate),
                30,
                pageHeight - 8
            )
            pdf.text(
                String(i) + ' / ' + String(pages),
                pageSize.getWidth() - 30,
                pageHeight - 8
            );
        }
        pdf.save(title + '.pdf');

        results.classList.remove('print-view');

        if (button) {
            button.removeAttribute('disabled');
            button.innerText = initialButtonText;
        }

        return Promise.resolve();
    },

    addDelayedInit(el, data, isAjax, isMultiple) {
        this.delayedQueue.push({
            el,
            data,
            isAjax,
            isMultiple,
            $el: $(el), // jQueried element (for performance reasons
            visible: false
        });

        if (this.delayedInterval === null) {
            this.delayedInterval = setInterval(() => {
                this.delayedQueue.forEach(item => {
                    if (item.$el.is(':visible')) {
                        this.initVoteEvaluation(item.el, item.data, item.isAjax, item.isMultiple);
                        item.visible = true;
                    }
                });

                this.delayedQueue = this.delayedQueue.filter(item => !item.visible);
                if (this.delayedQueue.length === 0) {
                    clearInterval(this.delayedInterval);
                }
            }, 100);
        }
    },
    initVoteEvaluation: async function (el, data, isAjax, isMultiple) {
        if ($(el).is(':not(:visible)')) {
            if (!$(el).data('vote-evaluation-delayed')) {
                this.addDelayedInit(el, data, isAjax, isMultiple);

                $(el).data('vote-evaluation-delayed', true);
            }

            return;
        }

        const Chartist = await STUDIP.loadChunk('chartist');

        jQuery(enhance);

        function enhance() {
            if (isMultiple) {
                new Chartist.Bar(
                    el,
                    data,
                    { onlyInteger: true, axisY: { onlyInteger: true } }
                );
            } else {
                data.series = data.series[0];
                new Chartist.Pie(
                    el,
                    data,
                    { labelPosition: 'outside' }
                );
            }
        }
    },
    initTestEvaluation: async function (el, data, isAjax, isMultiple) {
        this.initVoteEvaluation(el, data, isAjax, isMultiple);
    },
};

export default Questionnaire;
