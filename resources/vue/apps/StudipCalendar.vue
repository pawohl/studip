<template>
    <section class="studip-fullcalendar">
        <Teleport v-if="eventColourPicker" to="#sidebar-calendar-colour-picker"
                  name="sidebar-calendar-colour-picker">
            <section>
                <p class="info-text">
                    {{ $gettext('Im unteren Bereich können Sie bis zu 4 Farben frei wählen und diese via Drag & Drop auf Termine ziehen. Wenn Sie fertig sind, klicken Sie auf das Drucken-Symbol unter den Farbwählern.') }}
                </p>
                <div v-for="i in 4" class="colour-selector" style="background-color: #000000;"
                     :key="i" draggable="true"
                     @dragstart="startColourDragging($event)">
                    <input type="color" value="#000000" class="big-colour-input">
                </div>
                <StudipIcon class="text-bottom print-action" shape="print"
                            @click="printCalendar"
                            :title="$gettext('Individuelle Druckansicht drucken')"></StudipIcon>
            </section>
        </Teleport>
        <FullCalendar :options="calendar_options"
                      :class="all_extra_classes"
                      ref="fullCalendar">
            <template v-slot:dayHeaderContent="arg">
                <div v-if="['timeGridDay', 'timeGridWeek', 'resourceTimelineWeek', 'resourceTimelineDay'].includes(arg.view.type)"
                     class="day">
                        <div class="dow-short" v-html="getColumnDow(arg.date, true)"></div>
                        <div class="dow" v-html="getColumnDow(arg.date)"></div>
                    <template v-if="!isSemesterView(arg.view)">
                        <span class="date" v-html="getColumnDate(arg.date)"></span>
                        <div class="holiday" v-if="isHoliday(arg.date)">
                            {{ getHolidayName(arg.date) }}
                        </div>
                    </template>
                </div>
                <template v-if="arg.view.type === 'dayGridMonth'">
                    <div class="dow-short" v-html="getColumnDow(arg.date, true)"></div>
                    <div class="dow" v-html="getColumnDow(arg.date)"></div>
                </template>
            </template>
            <template v-slot:eventContent="arg">
                <section v-if="arg.event.display === 'auto' && ['timeGridDay', 'timeGridWeek'].includes(arg.view.type)"
                         :title="arg.event.title" class="event-content"
                         @drop="handleElementDropOnEvent($event)"
                         @dragenter.prevent @dragover.prevent>
                    <div class="fc-event-title-container">
                        <span v-if="arg.event.extendedProps['icons']" class="icons">
                            <StudipIcon v-for="icon of arg.event.extendedProps['icons']"
                                        :shape="icon" v-bind:key="icon"
                                        :style="{color: arg.event.textColor}"
                                        class="text-bottom"></StudipIcon>
                        </span>
                        <div v-if="arg.event.extendedProps['action-icons']"
                             class="action-icons" ref="action_icons">
                            <span v-for="[key, action] of Object.entries(arg.event.extendedProps['action-icons'])"
                                  v-bind:key="key">
                                <button :title="action.label"
                                        @click.stop="openActionIconUrlAsDialog(action)">
                                    <StudipIcon v-if="action.icon_name"
                                                :shape="action.icon_name"
                                                :style="{color: arg.event.textColor}"
                                                class="text-bottom"></StudipIcon>
                                </button>
                            </span>
                        </div>
                        <span v-if="arg.event.title" class="fc-event-title">
                            {{ arg.event.title }}
                        </span>
                        <span v-if="arg.event.extendedProps['title-lines']"
                              class="fc-event-title">
                            <div v-for="[key, line] of Object.entries(arg.event.extendedProps['title-lines'])"
                                 v-bind:key="key">
                                {{ line }}
                            </div>
                        </span>
                    </div>
                    <div v-if="!arg.event.allDay" class="fc-event-time">
                        {{ arg.timeText }}
                    </div>
                </section>
                <div v-if="arg.event.display === 'auto' && ['dayGridMonth', 'resourceTimelineWeek', 'resourceTimelineDay'].includes(arg.view.type)"
                     :style="{color: arg.event.textColor, backgroundColor: arg.event.backgroundColor, borderColor: arg.event.borderColor, width: '100%'}"
                     :title="arg.event.title">
                    <span v-if="['dayGridMonth', 'dayGridYear'].includes(arg.view.type)"
                          class="fc-event-time">
                        {{ arg.timeText }}
                    </span>
                    <span class="fc-event-title-container">
                        <span v-if="arg.event.extendedProps['icons']" class="icons">
                            <StudipIcon v-for="icon of arg.event.extendedProps['icons']" :shape="icon" v-bind:key="icon"
                                        class="text-bottom"></StudipIcon>
                        </span>
                        <span class="fc-event-title">
                        {{ arg.event.title }}
                        </span>
                    </span>
                </div>
                <div v-if="arg.event.display === 'background'"
                     :title="arg.event.title">
                    <div v-if="arg.event.extendedProps['generate-title']"
                         class="title">
                        {{ arg.event.title }}
                    </div>
                </div>
            </template>
        </FullCalendar>
    </section>
</template>
<script lang="ts">
import {defineComponent} from 'vue';
import FullCalendar from "@fullcalendar/vue3";
import {
    CalendarOptions, CalendarApi,
    DateSelectionApi, DatesSetArg,
    EventClickArg, EventDropArg, EventInput, ViewApi,
} from '@fullcalendar/core';
import {Draggable, EventReceiveArg, EventResizeDoneArg} from '@fullcalendar/interaction';

import {Action} from "../../assets/javascripts/lib/action";
import {holiday_cache} from "../../assets/javascripts/lib/holiday";
import datetime from "../../assets/javascripts/lib/datetime";
import Dialog from "../../assets/javascripts/lib/dialog.js";
import StudipIcon from "../components/StudipIcon.vue";
import {EventURLParameters, StudipCalendarConfig} from "../../assets/javascripts/lib/calendar";

export default defineComponent({
    name: "StudipCalendar",
    computed: {
        Dialog() {
            return Dialog
        }
    },
    components: {
        StudipIcon,
        FullCalendar
    },
    props: {
        config: {
            type: Object,
            required: true,
            default: () => ({})
        },
        actionUrls: {
            type: Object,
            required: false,
            default: () => ({})
        },
        dialogSize: {
            type: String,
            required: false,
            default: 'auto'
        },
        customEventHandlers: {
            type: Object,
            required: false,
            default: () => ({})
        },
        displayHolidays: {
            type: Boolean,
            required: false,
            default: true
        },
        displayVacations: {
            type: Boolean,
            required: false,
            default: true
        },
        extraClasses: {
            type: String,
            required: false,
            default: ''
        },
        externalDroppableContainerId: {
            type: String,
            required: false,
            default: ''
        },
        externalDroppableEventSelector: {
            type: String,
            required: false,
            default: ''
        },
        eventColourPicker: {
            type: Boolean,
            required: false,
            default: false
        }
    },
    emits: {
        eventDropped: (payload: EventDropArg) => payload,
        eventReceived: (payload: EventReceiveArg) => payload,
        eventResized: (payload: EventResizeDoneArg) => payload
    },
    setup() {
        const printCalendar = () => {
            window.print();
        };
        return {printCalendar};
    },
    data() {
        //Make sure that defaults are set for the calendar:
        const full_config = new StudipCalendarConfig(this.config);
        //Convert the configuration to an object that can be passed
        //to Fullcalendar later:
        const calendar_options = full_config.getConfig();
        const all_extra_classes : Array<string> = [];
        if (this.extraClasses.length > 0) {
            all_extra_classes.push(this.extraClasses);
        }
        if (this.displayVacations || this.displayHolidays) {
            all_extra_classes.push('with-holidays');
        }

        //Check if the responsive-design class is present in the HTML DOM node.
        //If so, start in the day view (if present) instead of the week view (if present).
        if (calendar_options.views !== undefined
            && calendar_options.views.timeGridDay !== undefined
            && calendar_options.views.timeGridWeek !== undefined
            && calendar_options.initialView === 'timeGridWeek') {
            const nodes = document.getElementsByTagName('html');
            if (nodes.length >= 1) {
                //Regard only the first node.
                const html_node = nodes[0];
                if (html_node.classList.contains('responsive-display')) {
                    //Start in day view:
                    calendar_options.initialView = 'timeGridDay';
                }
            }
        }

        //Now the event handlers for this component are set:
        const event_handlers = {
            datesSet:      this.handleCalendarRangeUpdate,
            eventDrop:     calendar_options.editable   ? this.handleEventDrop   : undefined,
            eventResize:   calendar_options.editable   ? this.handleEventResize : undefined,
            select:        calendar_options.selectable ? this.handleSelection   : undefined,
            eventClick:    this.handleEventClick,
            eventReceive:  this.handleEventReceive
        } as CalendarOptions;

        //Return the calendar options with other data:
        return {
            calendar_api: null as CalendarApi|null,
            calendar_options: {...calendar_options, ...event_handlers},
            all_extra_classes: all_extra_classes.join(' ')
        }
    },
    mounted() {
        const calendar = this.$refs.fullCalendar as typeof FullCalendar;
        if (calendar) {
            this.calendar_api = calendar.getApi();
        }
        this.initExternalDraggableItems();
        if (this.$refs.action_icons) {
            const element = this.$refs.action_icons as HTMLDivElement;
            element.addEventListener<"click">('click', function(event: Event) {
                event.preventDefault();
            });
        }
        //Check if there is a date selector with calendar control enabled.
        //In that case, the calendar shall change its date when the
        //date selector changes its value.
        const date_picker = document.querySelector('#date_select[data-calendar-control]') as HTMLElement;
        if (date_picker) {
            date_picker.onchange = this.useDateFromDatePicker;
        }
    },
    methods: {
        openActionIconUrlAsDialog(action: Action) {
            if (action.url) {
                Dialog.fromURL(action.url, {size: 'auto'});
            }
        },
        handleElementDropOnEvent(event: DragEvent) {
            if (!event.dataTransfer || !event.target) {
                return;
            }
            const drop_target = event.target as HTMLElement;
            const colour = event.dataTransfer.getData('colour');
            if (colour && drop_target) {
                //Colour the drop target:
                drop_target.style.backgroundColor = colour;
                //Colour the surrounding .fc-event element (for the border):
                const fc_event = drop_target.closest('.fc-event') as HTMLElement;
                if (fc_event) {
                    fc_event.style.backgroundColor = colour;
                    fc_event.style.borderColor = colour;
                }
            }
        },
        useDateFromDatePicker(event: Event) {
            if (!event.target || !this.calendar_api) {
                //Nothing to do.
                return;
            }
            const target = event.target as HTMLInputElement;
            if (!target) {
                //Still nothing to do.
                return;
            }
            if (!target.value) {
                //Positively still nothing to do.
                return;
            }
            const date_str = target.value;
            //The date format should be in the format dd.mm.YYYY.
            //But d.m.YYYY could also be acceptable.
            if (date_str.length < 8) {
                //Strange unsupported date format.
                return;
            }
            //The date string needs to be split into three parts and then used
            //as date parts for a Date object.
            const date_parts = date_str.split('.');
            if (date_parts.length != 3) {
                //Invalid date format.
                return;
            }
            const date = new Date();
            date.setFullYear(parseInt(date_parts[2]));
            date.setMonth(parseInt(date_parts[1]) - 1);
            date.setDate(parseInt(date_parts[0]));
            date.setHours(12);
            date.setMinutes(0);
            date.setSeconds(0);
            this.calendar_api.gotoDate(date);
        },
        startColourDragging(event: DragEvent) {
            if (!event.dataTransfer || !event.target) {
                return;
            }
            const colour_el = event.target as HTMLElement;
            if (!colour_el.style.backgroundColor) {
                return;
            }
            event.dataTransfer.dropEffect = 'move';
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('colour', colour_el.style.backgroundColor);
        },
        initExternalDraggableItems() {
            if (this.externalDroppableContainerId.length > 0
                && this.externalDroppableEventSelector.length > 0) {
                const container = document.getElementById(this.externalDroppableContainerId);
                if (container) {
                    new Draggable(
                        container,
                        {
                            itemSelector: this.externalDroppableEventSelector,
                            eventData: function (event_element) {
                                const event_attr = event_element.getAttribute('data-event');
                                if (event_attr) {
                                    return JSON.parse(event_attr);
                                }
                                return null;
                            }
                        }
                    );
                }
            }
        },
        getColumnDate(date: Date) {
            return datetime.getStudipDate(date, false, true, true);
        },
        getColumnDow(date: Date, short: boolean = false) {
            return datetime.getDayOfWeekName(date.getDay(), short);
        },
        isSemesterView(view: ViewApi) {
            if (['dayGridMonth', 'resourceTimelineWeek', 'resourceTimelineDay'].includes(view.type)) {
                //These views do not exist in semester views.
                return false;
            }
            const day_header_format = view.getOption('dayHeaderFormat');
            if (day_header_format === undefined) {
                //No day header format defined. Assume a semester view:
                return true;
            }
            if (day_header_format.standardDateProps.year && day_header_format.standardDateProps.month && day_header_format.standardDateProps.day) {
                //Year, month and day shall be displayed. This is not a semester view.
                return false;
            }
            //In all other cases, assume a semester view:
            return true;
        },
        isHoliday(date: Date) {
            if (date === undefined) {
                return false;
            }
            if (!this.displayHolidays && !this.displayVacations) {
                //Holidays are not displayed at all.
                return false;
            }
            if (this.displayHolidays) {
                return holiday_cache.isHoliday(date, this.displayVacations);
            } else {
                return holiday_cache.isVacation(date);
            }
        },
        getHolidayName(date: Date) {
            if (date === undefined) {
                return '';
            }
            if (!this.displayHolidays && !this.displayVacations) {
                //Holidays are not displayed at all.
                return '';
            }
            if (this.displayHolidays) {
                return holiday_cache.getHolidayName(date, this.displayVacations);
            } else {
                return holiday_cache.getVacationName(date);
            }
        },
        handleCalendarRangeUpdate: function(arg: DatesSetArg) : void {
            //Update the defaultDate URL parameter first:
            const url = new URL(window.location.href);
            //Set the defaultView parameter:
            let seconds = (arg.end.getTime() - arg.start.getTime()) / 1000;
            if (seconds > (86400 * 7) + 1) {
                //That is longer than a week, even if a leap second is in it.
                url.searchParams.set('defaultView', 'month');
                //Special handling for the month view: It starts on the beginning of a week
                //that does not lie on the first day of the month in most cases.
                const default_date = arg.start;
                if (default_date.getDate() != 1) {
                    //Go to the next month:
                    if (default_date.getMonth() < 11) {
                        default_date.setMonth(default_date.getMonth() + 1, 1);
                    } else {
                        //Go to the first January of the next year:
                        default_date.setFullYear(default_date.getFullYear() + 1);
                        default_date.setMonth(0, 1);
                    }
                }
                url.searchParams.set('defaultDate', datetime.getISODate(default_date));
            } else if (seconds <= 86401) {
                //This is one day, even if a leap second is in it.
                url.searchParams.set('defaultView', 'day');
                url.searchParams.set('defaultDate', datetime.getISODate(arg.start));
            } else {
                //This must be a week view.
                url.searchParams.set('defaultView', 'week');
                url.searchParams.set('defaultDate', datetime.getISODate(arg.start));
            }
            //Set the updated URLs parameters without reloading the page:
            window.history.replaceState(null, '', url.toString());

            if (this.displayHolidays || this.displayVacations) {
                //Make sure that all holidays and vacations are loaded for the range.
                //NOTE: This works only for views that span over a maximum of two years
                //like week and month views at the start/end of a year.
                if (this.displayHolidays) {
                    holiday_cache.loadHolidays(arg.view.activeStart.getFullYear());
                    holiday_cache.loadHolidays(arg.view.activeEnd.getFullYear());
                }
                if (this.displayVacations) {
                    holiday_cache.loadVacations(arg.view.activeStart.getFullYear());
                    holiday_cache.loadVacations(arg.view.activeEnd.getFullYear());
                }
            }
            if (this.isSemesterView(arg.view)) {
                //Remove the navigation button if the view is not timeGridDay:
                const end_nav = document.querySelector(':nth-last-child(1 of .fc-toolbar-chunk)') as HTMLElement;
                if (end_nav) {
                    if (arg.view.type === 'timeGridDay') {
                        //Show the navigation buttons.
                        end_nav.style.display = 'initial';
                    } else {
                        //Hide the navigation buttons.
                        end_nav.style.display = 'none';
                    }
                }
            }
        },
        handleSelection: function(selection: DateSelectionApi) {
            if (!this.calendar_options.editable || this.actionUrls.length < 1) {
                //The calendar isn't editable.
                return;
            }
            const data = new EventURLParameters();
            data.start   = selection.start;
            data.end     = selection.end;
            data.all_day = selection.allDay;
            if (selection.resource) {
                data.setResourceId(selection.resource.id);
            }
            if (this.actionUrls['add']) {
                //Add the selected time range to the URL and load it
                //in a dialog:
                Dialog.fromURL(
                    this.actionUrls['add'],
                    {
                        data: data.toObject(),
                        size: this.dialogSize
                    }
                );
            }
        },
        handleEventClick: function(event_data: EventClickArg) {
            if (!event_data.event.extendedProps.studip_view_urls
                || !event_data.event.extendedProps.studip_view_urls.show) {
                //Nothing to do.
                return;
            }
            //Load the dialog:
            Dialog.fromURL(
                event_data.event.extendedProps.studip_view_urls.show,
                {
                    size: this.dialogSize
                }
            );
        },
        handleEventDrop: function(drop_arg: EventDropArg) {
            if (!this.calendar_options.editable || !drop_arg.event.startEditable
                || !drop_arg.event.start ||
                (drop_arg.oldEvent.allDay === drop_arg.event.allDay && !drop_arg.event.end)) {
                //Nothing to do.
                return;
            }
            const data = new EventURLParameters(drop_arg.event);
            if (data.start === null) {
                //Something went wrong. We cannot continue.
                return;
            }
            if (drop_arg.oldEvent.allDay && !drop_arg.event.allDay) {
                //An all-day event has become a date with a time range.
                //Construct an end date for it 1 hour after the start.
                data.setEnd(new Date(data.start.getTime() + 3600000));
                //Set the end to the event object, too so that it can be dragged some more:
                drop_arg.event.setEnd(data.end);
            }
            if (drop_arg.newResource) {
                data.setResourceId(drop_arg.newResource.id);
            }
            if (drop_arg.event.extendedProps.studip_api_urls.move) {
                //Call the move URL as HTTP POST:
                $.post({
                    async: false,
                    url: drop_arg.event.extendedProps.studip_api_urls.move,
                    data: data.toObject()
                })
                    .fail(drop_arg.revert)
                    .done(() => {
                    //Reload all calendar events so that their
                    //move-URLs are also updated.
                    drop_arg.view.calendar.refetchEvents();
                });
            } else if (drop_arg.event.extendedProps.studip_view_urls.move_dialog) {
                //Show the move dialog:
                Dialog.fromURL(
                    drop_arg.event.extendedProps.studip_view_urls.move_dialog,
                    {
                        data: data.toObject(),
                        size: this.dialogSize
                    }
                );
            }
            this.$emit('eventDropped', drop_arg as EventDropArg);
        },
        handleEventResize: function(resize_arg: EventResizeDoneArg) {
            if (!this.calendar_options.editable || !resize_arg.event.startEditable
                || !resize_arg.event.start || !resize_arg.event.end) {
                //Nothing to do.
                return;
            }
            const data = new EventURLParameters(resize_arg.event);
            if (resize_arg.event.extendedProps.studip_api_urls.resize) {
                //Call the move URL as HTTP POST:
                $.post({
                    async: false,
                    url: resize_arg.event.extendedProps.studip_api_urls.resize,
                    data: data.toObject()
                })
                    .fail(resize_arg.revert)
                    .done(() => {
                        //Reload all calendar events so that their
                        //resize-URLs are also updated.
                        resize_arg.view.calendar.refetchEvents();
                    });
            } else if (resize_arg.event.extendedProps.studip_view_urls.resize_dialog) {
                Dialog.fromURL(
                    resize_arg.event.extendedProps.studip_view_urls.resize_dialog,
                    {
                        data: data.toObject(),
                        size: this.dialogSize
                    }
                );
            }
            this.$emit('eventResized', resize_arg as EventResizeDoneArg);
        },
        handleEventReceive(receive_arg: EventReceiveArg) {
            if (!receive_arg.event || !receive_arg.event.start || !receive_arg.event.end) {
                //Nothing to do except of reverting the event:
                receive_arg.revert();
                return;
            }
            const data = new EventURLParameters();
            data.setStart(receive_arg.event.start);
            data.setEnd(receive_arg.event.end);
            data.setAllDay(receive_arg.event.allDay);
            if (receive_arg.event.extendedProps.studip_api_urls.receive) {
                $.post({
                    async: false,
                    url: receive_arg.event.extendedProps.studip_api_urls.receive,
                    data: data.toObject()
                })
                    .fail(receive_arg.revert)
                    .done(data => {
                        //Add the event that has been created and remove the
                        //temporary event from the drop.
                        const event_data = JSON.parse(data);
                        if (event_data) {
                            receive_arg.view.calendar.addEvent(event_data as EventInput);
                            receive_arg.event.remove();
                        }
                    });
            } else if (receive_arg.event.extendedProps.studip_view_urls.receive_dialog) {
                Dialog.fromURL(
                    receive_arg.event.extendedProps.studip_view_urls.receive_dialog,
                    {
                        data: data.toObject(),
                        size: this.dialogSize
                    }
                );
            }
            this.$emit('eventReceived', receive_arg);
        }
    }
})
</script>
<style lang="scss">
@import '../../assets/stylesheets/scss/fullcalendar';
</style>
