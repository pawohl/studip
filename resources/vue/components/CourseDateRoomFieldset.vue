<template>
    <fieldset>
        <legend>{{ $gettext('Raumangaben') }}</legend>

        <section v-if="room_management_enabled">
            <studip-message-box v-if="selected_room_option === 'room' && available_rooms.length === 0 && searched_for_rooms"
                                hide-close="true">
                {{ $gettext('Im gewählten Zeitbereich sind keine buchbaren Räume verfügbar.') }}
            </studip-message-box>
            <label>
                <input type="radio" name="room"
                       v-model="selected_room_option"
                       value="room">
                {{ $gettext('Gebuchte Räume') }}
            </label>
            <label v-if="selected_room_option === 'room' && available_rooms.length > 0" for="room_ids[]">
                {{ $gettext('Raum auswählen') }}
            </label>
            <span class="flex-row">
                <StudipSelect v-model="selected_room_list"
                              :no_options_text="$gettext('Kein Raum verfügbar')"
                              :options="available_rooms"
                              multiple
                              style="flex-grow: 2"
                              @click="selected_room_option = 'room'">
                    <template #selected-option="{id, label}">
                        <span>{{ label }}</span>
                        <input type="hidden" name="room_ids[]" :value="id">
                    </template>
                </StudipSelect>
                <studip-icon v-if="show_ajax_indicator" shape="reload" role="info"></studip-icon>
            </span>
            <section v-if="selected_room_option === 'room' && available_rooms.length > 0 && Object.keys(visible_info_texts).length > 0">
                <h3>{{ $gettext('Hinweise zu teilbaren Räumen') }}</h3>
                <ul class="default">
                    <li v-for="item in visible_info_texts" v-bind:key="item">
                        {{ item }}
                    </li>
                </ul>
            </section>
            <label v-if="selected_room_option === 'room' && available_rooms.length > 0">
                {{ $gettext('Rüstzeit vor dem Termin (in Minuten)') }}
                <input type="number" name="preparation_time"
                       class="preparation-time"
                       v-model="preparation_time"
                       min="0"
                       :max="max_preparation_time">
            </label>
            <label v-if="selected_room_option === 'room' && available_rooms.length > 0">
                {{ $gettext('Rüstzeit nach dem Termin (in Minuten)') }}
                <input type="number" name="subsequent_time"
                       class="preparation-time"
                       v-model="subsequent_time"
                       min="0"
                       :max="max_preparation_time">
            </label>
        </section>

        <label>
            <input type="radio" name="room"
                   v-model="selected_room_option"
                   value="freetext">
            {{ $gettext('Freie Ortsangabe (keine Raumbuchung)') }}
        </label>
        <label v-if="selected_room_option === 'freetext'">
            <input type="text" name="room_name"
                   v-model="room_name"
                   :placeholder="$gettext('Freie Ortsangabe (keine Raumbuchung)')">
        </label>

        <label>
            <input type="radio" name="room"
                   v-model="selected_room_option"
                   value="noroom">
            {{ $gettext('Kein Raum') }}
        </label>
        <label v-if="show_nochange_option">
            <input type="radio" name="room"
                   v-model="selected_room_option"
                   value="nochange">
            {{ $gettext('Keine Änderung') }}
        </label>
    </fieldset>
</template>
<script>
import {$gettext} from "../../assets/javascripts/lib/gettext";
import StudipMessageBox from "../components/StudipMessageBox.vue";
import StudipSelect from "../components/StudipSelect.vue";
import StudipIcon from "../components/StudipIcon.vue";
import {jsonapi} from "../../assets/javascripts/lib/jsonapi";

export default {
    name: 'CourseDateRoomFieldset',
    components: {StudipMessageBox, StudipSelect, StudipIcon},
    props: {
        time_ranges: {
            type: Array,
            required: true,
            default: () => []
        },
        course_date_ids: {
            type: Array,
            required: false,
            default: () => []
        },
        room_management_enabled: {
            type: Boolean,
            required: true,
            default: false
        },
        allow_multiple_room_bookings: {
            type: Boolean,
            required: false,
            default: false
        },
        max_preparation_time: {
            type: Number,
            required: false,
            default: 999
        },
        initial_selected_rooms: {
            type: Array,
            required: false,
            default: () => []
        },
        initial_selected_room_option: {
            type: String,
            required: false,
            default: 'nochange'
        },
        show_nochange_option: {
            type: Boolean,
            required: false,
            default: false
        },
        initial_preparation_time: {
            type: Number,
            required: false,
            default: 0
        },
        initial_subsequent_time: {
            type: Number,
            required: false,
            default: 0
        },
        initial_room_name: {
            type: String,
            required: false,
            default: ''
        }
    },
    data() {
        let room_option = this.initial_selected_room_option;
        if (!this.show_nochange_option) {
            room_option = 'noroom';
        }
        return {
            searched_for_rooms: false,
            available_rooms: [],
            preparation_time: this.initial_preparation_time,
            subsequent_time: this.initial_subsequent_time,
            room_name: this.initial_room_name,
            info_texts: [],
            selected_room_list: this.initial_selected_rooms !== undefined ? this.initial_selected_rooms : [],
            selected_room_option: room_option,
            show_ajax_indicator: false
        }
    },
    methods: {
        $gettext,
        getAvailableRooms() {
            if (this.selected_room_option !== 'room') {
                //We don't need to look for available rooms.
                return;
            }

            //Reload the list of available rooms:
            this.show_ajax_indicator = true;
            try {
                const options = {
                    method: 'GET',
                    data: {
                        time_ranges: JSON.stringify(this.time_ranges)
                    },
                    async: true
                };
                if (this.course_date_ids) {
                    options.data.course_date_ids = this.course_date_ids;
                }
                jsonapi.request('available-rooms', options).then((response) => {
                    const json = JSON.parse(response);
                    if (!json) {
                        //Error fetching the available rooms.
                        this.available_rooms = [];
                        this.searched_for_rooms = true;
                    }
                    //Change the format for the multiselect and strip the info text.
                    this.available_rooms          = [];
                    let available_room_ids        = [];
                    this.info_texts               = {};
                    let current_separable_room_id = '';
                    for (let item of json) {
                        //$item is an object with the attributes id, name and info_text.
                        if (item.id.startsWith('separable_room-')) {
                            //It is a separable room.
                            this.available_rooms.push(
                                {
                                    id: item.id,
                                    label: item.name,
                                    indented: false,
                                    separable_room_id: item.separable_room_id
                                }
                            );
                            available_room_ids.push(item.id);
                            current_separable_room_id = item.id.substring(15);
                        } else if (item.separable_room_id) {
                            //Indent the name of the room part of the separable room:
                            this.available_rooms.push(
                                {
                                    id: item.id,
                                    label: item.name,
                                    indented: current_separable_room_id.length > 0,
                                    separable_room_id: item.separable_room_id
                                }
                            );
                            available_room_ids.push(item.id);
                        } else {
                            //A room that is not part of a separable room.
                            current_separable_room_id = '';
                            this.available_rooms.push(
                                {
                                    id: item.id,
                                    label: item.name,
                                    indented: false,
                                    separable_room_id: null
                                }
                            );
                            available_room_ids.push(item.id);
                        }
                        if (item.info_text && item.info_text.length > 0 && item.separable_room_id) {
                            this.info_texts[item.id] = {
                                separable_room_id: item.separable_room_id,
                                info_text: item.info_text
                            };
                        }
                    }
                    this.searched_for_rooms = true;
                    //Update the selected rooms: If a room is not present in the list of available rooms,
                    //it shall be removed from the list.
                    let new_selected_rooms = [];
                    for (let selected_room of this.selected_room_list) {
                        if (available_room_ids.includes(selected_room.id)) {
                            new_selected_rooms.push(selected_room);
                        }
                    }
                    this.selected_room_list = new_selected_rooms;
                });
            } catch (error) {
                console.error(error);
                //Clear the list of available rooms, since we cannot determine
                //if the current list is accurate.
                this.available_rooms = [];
                this.searched_for_rooms = true;
            }
            this.show_ajax_indicator = false;
        },
        reviewSelectedRooms() {
            if (this.allow_multiple_room_bookings) {
                //Nothing to review here, since the user may book multiple rooms.
                return;
            }
            //In case code execution reaches this point, the user is not allowed
            //to book multiple rooms. This requires a review of the
            //selected room list.
            if (this.selected_room_list.length > 1) {
                //Remove all items until the last which is the most recently
                //added room.
                let processed_new_list = [];
                processed_new_list.push(this.selected_room_list.at(-1));
                this.selected_room_list = processed_new_list;
            }
        }
    },
    computed: {
        visible_info_texts() {
            let new_visible_info_texts = {};
            for (let item of this.selected_room_list) {
                if (item.separable_room_id && this.info_texts[item.id]) {
                    let item_info_text = this.info_texts[item.id];
                    new_visible_info_texts[item_info_text.separable_room_id] = item_info_text.info_text;
                }
            }
            return new_visible_info_texts;
        }
    },
    watch: {
        time_ranges(new_ranges, old_ranges) {
            if (old_ranges === undefined || old_ranges === new_ranges) {
                //Do nothing.
                return;
            }
            if (this.selected_room_option === 'room') {
                this.getAvailableRooms();
            }
        },
        selected_room_option(new_options, old_options) {
            if (old_options === undefined || old_options === new_options) {
                //Do nothing.
                return;
            }
            if (this.selected_room_option === 'room') {
                this.getAvailableRooms();
            }
        },
        selected_room_list(new_list, old_list) {
            if (old_list === new_list) {
                //Do nothing.
                return;
            }
            this.reviewSelectedRooms();
        }
    }
}
</script>
