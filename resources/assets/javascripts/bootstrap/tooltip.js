// Attach global hover handler for tooltips.
// Applies to all elements having a "data-tooltip" attribute.
// Tooltip may be provided in the data-attribute itself or by
// defining a title attribute. The latter is prefered due to
// the obvious accessibility issues.

let timeout = null;

STUDIP.Tooltip.threshold = 6;

$(document).on('mouseenter mouseleave focusin focusout', '[data-tooltip],.tooltip:has(.tooltip-content)', function(event) {
    let data = $(this).data();

    const inTopBar = $(this).parents('#top-bar').length > 0;
    const visible = ['mouseenter', 'focusin'].includes(event.type);
    const offset = $(this).offset();
    const x = offset.left + $(this).outerWidth(true) / 2;
    const y = inTopBar ? offset.top + 160 : offset.top;
    const delay = data.tooltipDelay ?? 300;
    const tooltipClasses = inTopBar ? 'studip-tooltip studip-tooltip-top' : '';

    let content;
    let tooltip;

    if (!data.tooltipObject) {
        // If tooltip has not yet been created (first hover), obtain it's
        // contents and create the actual tooltip object.
        if (!data.tooltip || !$.isPlainObject(data.tooltip)) {
            content = $('<div/>').text(data.tooltip || $(this).attr('title')).html();
        } else if (data.tooltip.html !== undefined) {
            content = data.tooltip.html;
        } else if (data.tooltip.text !== undefined) {
            content = data.tooltip.text;
        } else {
            throw "Invalid content for tooltip via data";
        }
        if (!content) {
            content = $(this).find('.tooltip-content').remove().html();
        }
        $(this).attr('title', null);
        $(this).attr('data-tooltip', content);

        tooltip = new STUDIP.Tooltip(x, y, content, tooltipClasses);
        tooltip.connectWithDOMElement(this);

        data.tooltipObject = tooltip;

        if (!this.hasAttribute('aria-label')) {
            const div = document.createElement('div');
            div.innerHTML = content;
            this.setAttribute('aria-label', div.innerText.trim());
        }

        $(this).on('remove', function() {
            tooltip.remove();
        });
    } else if (visible) {
        // If tooltip has already been created, update it's position.
        // This is neccessary if the surrounding content is scrollable AND has
        // been scrolled. Otherwise the tooltip would appear at it's previous
        // and now wrong location.
        data.tooltipObject.position(x, y);
    }

    if (visible) {
        $('.studip-tooltip').not(data.tooltipObject).hide();
        data.tooltipObject.show();
    } else {
        timeout = setTimeout(() => data.tooltipObject.hide(), delay);
    }
}).on('mouseenter focusin', '.studip-tooltip', () => {
    clearTimeout(timeout);
}).on('mouseleave focusout', '.studip-tooltip', function() {
    $(this).hide();
});
