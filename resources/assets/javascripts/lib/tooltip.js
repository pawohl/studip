import CSS from './css.js';

/**
 * Tooltip library for Stud.IP
 *
 * @author Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @copyright Stud.IP Core Group 2014
 * @license GPL2 or any later version
 * @since Stud.IP 3.1
 */

let count = 0;
let threshold = 0;

class Tooltip {
    static get count() {
        return count;
    }

    static set count(value) {
        count = value;
    }

    // Threshold used for "edge detection" (imagine a padding along the edges)
    static get threshold() {
        return threshold;
    }

    static set threshold(value) {
        threshold = value;
    }

    /**
     * Returns a new unique id of a tooltip.
     *
     * @return {string} Unique id
     * @static
     */
    static getId() {
        const id = `studip-tooltip-${Tooltip.count}`;
        Tooltip.count += 1;
        return id;
    }

    /**
     * Constructs a new tooltip at given location with given content.
     * The applied css class may be changed by the fourth parameter.
     *
     * @class
     * @classdesc Stud.IP tooltips provide an improved layout and handling
     *            of contents (including html) than the browser's default
     *            tooltip through title attribute would
     *
     * @param {int} x - Horizontal position of the tooltip
     * @param {int} y - Vertical position of the tooltip
     * @param {string} content - Content of the tooltip (may be html)
     * @param {string} css_class - Optional name of the applied css class /
     *                             defaults to 'studip-tooltip'
     */
    constructor(x, y, content, css_class) {
        this.domElement = null;

        // Obtain unique id of the tooltip
        this.id = Tooltip.getId();

        // Create dom element of the tooltip, apply id and class and attach
        // to dom
        this.element = $('<div>');
        this.element.addClass(css_class || 'studip-tooltip');
        this.element.attr('id', this.id);
        this.element.attr('role', 'tooltip');
        this.element.appendTo('body');

        // Create bound event handlers
        this.boundKeyHandler = this.#keyHandler.bind(this);
        this.boundClickHandler = this.#clickHandler.bind(this);

        // Set position and content and paint the tooltip
        this.position(x, y);
        this.update(content);
        this.paint();
    }

    connectWithDOMElement(element) {
        this.domElement = element;
        this.domElement.addEventListener('click', this.boundClickHandler);
    }

    /**
     * Translates the arrow(s) under a tooltip using css3 translate
     * transforms. This is needed at the edges of the screen.
     * This implies that a current browser is used. The translation could
     * also be achieved by adjusting margins but that way we would need
     * to hardcode values into this function since it's a struggle to
     * obtain the neccessary values from the CSS pseudo selectors in JS.
     *
     * Internal, css rules are dynamically created and applied to the current
     * document by using the methods provided in the file studip-css.js.
     *
     * @param {int} x - Horizontal offset
     * @param {int} y - Vertical offset
     * @param {boolean} left_arrow
     */
    translateArrows(x, y, left_arrow = false) {
        CSS.removeRule(`#${this.id}::before`);
        CSS.removeRule(`#${this.id}::after`);

        if (x !== 0 || y !== 0) {
            let before_rule = {
                transform: `translate(${x}px, ${y}px);`
            };
            if (left_arrow) {
                before_rule.transform = `translate(${x}px, ${y}px) rotate(90deg);`;
            }
            let after_rule = before_rule;
            if (left_arrow) {
                after_rule['border-width'] = '9px';
            }
            CSS.addRule(`#${this.id}::before`, before_rule, ['-ms-', '-webkit-']);
            CSS.addRule(`#${this.id}::after`, after_rule, ['-ms-', '-webkit-']);
        }
    }

    /**
     * Updates the position of the tooltip.
     *
     * @param {int} x - Horizontal position of the tooltip
     * @param {int} y - Vertical position of the tooltip
     */
    position(x, y) {
        this.x = x;
        this.y = y;
    }

    /**
     * Updates the contents of the tooltip.
     *
     * @param {string} content - Content of the tooltip (may be html)
     */
    update(content) {
        this.element.html(content);
    }

    /**
     * "Paints" the tooltip. This method actually computes the dimensions of
     * the tooltips, checks for screen edges and calculates the actual offset
     * in the current document.
     * This method is neccessary due to the fact that position and content
     * can be changed apart from each other.
     * Thus: Don't forget to repaint after adjusting any of the two.
     */
    paint() {
        const width = this.element.outerWidth(true);
        const height = this.element.outerHeight(true);
        const maxWidth = $(document).width();
        const maxHeight = $(document).height();
        let x = this.x - width / 2;
        let y = this.y - height;
        //The arrow offset is the offset from the bottom right corner of
        //the tooltip "frame".
        let arrow_offset_x = 0;
        let arrow_offset_y = 0;
        let left_arrow = false;

        if (y < 0) {
            y = 0;
            x = this.x + 20;
            //Put the arrow on the left side and move the tooltip,
            //if there is still enough place left on the right.
            left_arrow = true;
            arrow_offset_y = -height + this.y + 10;
            if (arrow_offset_y > -20) {
                y+= arrow_offset_y + 20;
                arrow_offset_y = -20;
            }
            arrow_offset_x = -width / 2 - 8;
        } else if (y + height > maxHeight) {
            y = maxHeight - height;
        }

        if (x < 0) {
            arrow_offset_x = 0;
            x = 0;
        } else if (x + width > maxWidth) {
            arrow_offset_x = x + width - maxWidth;
            x = maxWidth - width;
        }
        this.translateArrows(arrow_offset_x, arrow_offset_y, left_arrow);

        this.element.css({
            left: x,
            top: y
        });
    }

    /**
     * Toggles the visibility of the tooltip. If no state is provided,
     * the tooltip will be hidden if visible and vice versa. Pretty straight
     * forward and no surprises here.
     * This method implicitely calls paint before a tooltip is shown (in case
     * it was forgotten).
     *
     * @param {boolean|null} visible - Optional visibility parameter to set the
     *                         tooltip to a certain state
     */
    toggle(visible = null) {
        visible = visible ?? !this.element.is(':visible');

        if (visible) {
            document.addEventListener(
                'keydown',
                this.boundKeyHandler,
                // Capture: tooltips inside dialogs should consume Escape before the dialog closes.
                true
            );
            this.paint();
        } else {
            document.removeEventListener(
                'keydown',
                this.boundKeyHandler,
                true
            );
        }
        this.element.toggle(visible);
    }

    /**
     * Reveals the tooltip.
     *
     * @see Tooltip.toggle
     */
    show() {
        this.toggle(true);
    }

    /**
     * Hides the tooltip.
     *
     * @see Tooltip.toggle
     */
    hide() {
        this.toggle(false);
    }

    /**
     * Removes the tooltip
     */
    remove() {
        // Hide first to remove global event listener
        this.hide();

        this.domElement?.removeEventListener('click', this.boundClickHandler);

        this.element.remove();
    }

    #keyHandler(event) {
        if (event.key !== 'Escape') {
            return;
        }

        this.hide();

        event.preventDefault();
        event.stopPropagation();
    }

    #clickHandler() {
        this.toggle();
    }
}

export default Tooltip;
