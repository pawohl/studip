<template>
    <div class="formpart">
        <section>
            <label>{{ $gettext('Art der Wiederholung') }}
                <select :name="name + '_type'" v-model="repetition_type_value">
                    <option value="SINGLE" :selected="!repetition_type_value || repetition_type_value === 'SINGLE'">
                        {{ $gettext('Keine Wiederholung') }}
                    </option>
                    <option value="DAILY" :selected="repetition_type_value === 'DAILY'">
                        {{ $gettext('Tägliche Wiederholung') }}
                    </option>
                    <option value="WORKDAYS" :selected="repetition_type_value === 'WORKDAYS'">
                        {{ $gettext('Wiederholung an jedem Werktag') }}
                    </option>
                    <option value="WEEKLY" :selected="repetition_type_value === 'WEEKLY'">
                        {{ $gettext('Wöchentliche Wiederholung') }}
                    </option>
                    <option value="MONTHLY" :selected="repetition_type_value === 'MONTHLY'">
                        {{ $gettext('Monatliche Wiederholung') }}
                    </option>
                    <option value="YEARLY" :selected="repetition_type_value === 'YEARLY'">
                        {{ $gettext('Jährliche Wiederholung') }}
                    </option>
                </select>
            </label>
        </section>
        <section v-if="repetition_type_value === 'DAILY'">
            <LabelRequired :label="$gettext('Abstand in Tagen')">
                <input type="number" min="1" required :name="name + '_interval'"
                       v-model="repetition_interval_value">
            </LabelRequired>
        </section>
        <section v-else-if="repetition_type_value === 'WEEKLY'">
            <LabelRequired :label="$gettext('Abstand in Wochen')">
                <input type="number" min="1" required :name="name + '_interval'"
                       v-model="repetition_interval_value">
            </LabelRequired>
            <div>
                <p>{{ $gettext('Wiederholung an bestimmten Wochentagen') }}</p>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="1" :checked="repetition_dow_value.includes('1')">
                    {{ $gettext('Montag') }}
                </label>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="2" :checked="repetition_dow_value.includes('2')">
                    {{ $gettext('Dienstag') }}
                </label>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="3" :checked="repetition_dow_value.includes('3')">
                    {{ $gettext('Mittwoch') }}
                </label>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="4" :checked="repetition_dow_value.includes('4')">
                    {{ $gettext('Donnerstag') }}
                </label>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="5" :checked="repetition_dow_value.includes('5')">
                    {{ $gettext('Freitag') }}
                </label>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="6" :checked="repetition_dow_value.includes('6')">
                    {{ $gettext('Samstag') }}
                </label>
                <label>
                    <input type="checkbox" :name="name + '_dow[]'"
                           value="7" :checked="repetition_dow_value.includes('7')">
                    {{ $gettext('Sonntag') }}
                </label>
            </div>
        </section>
        <section v-else-if="repetition_type_value === 'YEARLY'">
            <LabelRequired :label="$gettext('Abstand in Jahren')">
                <input type="number" min="1" required :name="name + '_interval'"
                       v-model="repetition_interval_value">
            </LabelRequired>
            <label>
                {{ $gettext('Art der jährlichen Wiederholung') }}
                <select :name="name + '_month_type'"
                        v-model="repetition_month_type_value">
                    <option value="dom"
                            :selected="repetition_month_type_value === 'dom'">
                        {{ $gettext('Wiederholung an einem bestimmten Datum') }}
                    </option>
                    <option value="dow"
                            :selected="repetition_month_type_value === 'dow'">
                        {{ $gettext('Wiederholung an einem bestimmten Wochentag') }}
                    </option>
                </select>
            </label>
            <label v-if="repetition_month_type_value === 'dom'">
                {{ $gettext('Tag') }}
                <input type="number" :name="name + '_dom'" min="1" max="31" v-model="repetition_dom_value">
            </label>
            <label>
                {{ $gettext('Monat') }}
                <select :name="name + '_month'"
                        v-model="repetition_month_value">
                    <option value="1" :selected="repetition_month_value === 1">
                        {{ $gettext('Januar') }}
                    </option>
                    <option value="2" :selected="repetition_month_value === 2">
                        {{ $gettext('Februar') }}
                    </option>
                    <option value="3" :selected="repetition_month_value === 3">
                        {{ $gettext('März') }}
                    </option>
                    <option value="4" :selected="repetition_month_value === 4">
                        {{ $gettext('April') }}
                    </option>
                    <option value="5" :selected="repetition_month_value === 5">
                        {{ $gettext('Mai') }}
                    </option>
                    <option value="6" :selected="repetition_month_value === 6">
                        {{ $gettext('Juni') }}
                    </option>
                    <option value="7" :selected="repetition_month_value === 7">
                        {{ $gettext('Juli') }}
                    </option>
                    <option value="8" :selected="repetition_month_value === 8">
                        {{ $gettext('August') }}
                    </option>
                    <option value="9" :selected="repetition_month_value === 9">
                        {{ $gettext('September') }}
                    </option>
                    <option value="10" :selected="repetition_month_value === 10">
                        {{ $gettext('Oktober') }}
                    </option>
                    <option value="11" :selected="repetition_month_value === 11">
                        {{ $gettext('November') }}
                    </option>
                    <option value="12" :selected="repetition_month_value === 12">
                        {{ $gettext('Dezember') }}
                    </option>
                </select>
            </label>
        </section>
        <section v-if="repetition_type_value === 'MONTHLY'">
            <LabelRequired :label="$gettext('Abstand in Monaten')">
                <input type="number" min="1" required :name="name + '_interval'"
                       v-model="repetition_interval_value">
            </LabelRequired>
            <label>
                {{ $gettext('Art der monatlichen Wiederholung') }}
                <select :name="name + '_month_type'"
                        v-model="repetition_month_type_value">
                    <option value="dom" :selected="repetition_month_type_value === 'dom'">
                        {{ $gettext('Wiederholung an einem bestimmten Tag des Monats') }}
                    </option>
                    <option value="dow" :selected="repetition_month_type_value === 'dow'">
                        {{ $gettext('Wiederholung an einem bestimmten Wochentag') }}
                    </option>
                </select>
            </label>
        </section>
        <section v-if="repetition_type_value === 'MONTHLY' && repetition_month_type_value === 'dom'">
            <label>
                {{ $gettext('Wiederholung an einem bestimmten Tag des Monats') }}
                <input type="number" min="1" :name="name + '_dom'"
                       v-model="repetition_dom_value">
            </label>
        </section>
        <section v-if="['MONTHLY', 'YEARLY'].includes(repetition_type_value) && repetition_month_type_value === 'dow'">
            <label>
                {{ $gettext('Wiederholung an einem bestimmten Wochentag') }}
                <day-of-week-select :name="name + '_dow'" :value="repetition_dow_value[0]"
                                    :with_indeterminate="true"></day-of-week-select>
            </label>
            <label>
                {{ $gettext('Wann im Monat soll die Wiederholung stattfinden?') }}
                <select :name="name + '_dow_week'">
                    <option value="" :selected="!repetition_dow_week_value">
                        {{ $gettext('Bitte wählen') }}
                    </option>
                    <option value="1" :selected="repetition_dow_week_value === 1">
                        {{ $gettext('Am ersten gewählten Wochentag') }}
                    </option>
                    <option value="2" :selected="repetition_dow_week_value === 2">
                        {{ $gettext('Am zweiten gewählten Wochentag') }}
                    </option>
                    <option value="3" :selected="repetition_dow_week_value === 3">
                        {{ $gettext('Am dritten gewählten Wochentag') }}
                    </option>
                    <option value="4" :selected="repetition_dow_week_value === 4">
                        {{ $gettext('Am vierten gewählten Wochentag') }}
                    </option>
                    <option value="-1" :selected="repetition_dow_week_value === -1">
                        {{ $gettext('Am letzten gewählten Wochentag') }}
                    </option>
                </select>
            </label>
        </section>

        <section v-if="repetition_type_value !== 'SINGLE'">
            <label>
                {{ $gettext('Ende der Wiederholung') }}
                <select :name="name + '_rep_end_type'"
                        v-model="repetition_end_type_value">
                    <option value="" :selected="!repetition_end_type_value">
                        {{ $gettext('Nie') }}
                    </option>
                    <option value="end_date" :selected="repetition_end_type_value === 'end_date'">
                        {{ $gettext('An einem bestimmten Datum') }}
                    </option>
                    <option value="end_count" :selected="repetition_end_type_value === 'end_count'">
                        {{ $gettext('Nach einer Anzahl von Terminen') }}
                    </option>
                </select>
            </label>
        </section>
        <section v-if="repetition_end_type_value === 'end_date'">
            <label>
                {{ $gettext('Enddatum') }}
                <input type="text" :name="name + '_rep_end_date'"
                       data-date-picker v-model="repetition_end_date_value">
            </label>
        </section>
        <section v-else-if="repetition_end_type_value === 'end_count'">
            <label>
                {{ $gettext('Anzahl der Termine') }}
                <input type="number" min="1" :name="name + '_number_of_dates'"
                       v-model="number_of_dates_value">
            </label>
        </section>
    </div>
</template>

<script>
import LabelRequired from '../forms/LabelRequired.vue';

export default {
    name: "repetition-input",
    components: { LabelRequired },
    emits: [
        'input_number_of_dates',
        'input_repetition_dom',
        'input_repetition_dow',
        'input_repetition_dow_week',
        'input_repetition_end_date',
        'input_repetition_end_type',
        'input_repetition_interval',
        'input_repetition_month',
        'input_repetition_month_type',
        'input_repetition_type',
    ],
    props: {
        name: {
            type: String,
            required: true
        },
        default_date: {
            type: String,
            required: true
        },
        repetition_type: {
            type: String,
            required: true
        },
        repetition_interval: {
            type: String,
            required: true
        },
        repetition_dow: {
            type: Array,
            required: true
        },
        repetition_dow_week: {
            type: Number,
            required: true
        },
        repetition_month: {
            type: Number,
            required: true
        },
        repetition_month_type: {
            type: String,
            required: false
        },
        repetition_dom: {
            type: Number,
            required: true
        },
        repetition_end_type: {
            type: String,
            required: false
        },
        repetition_end_date: {
            type: String,
            required: true
        },
        number_of_dates: {
            type: Number,
            required: true
        }
    },
    data () {
        return {
            repetition_type_value: '',
            repetition_interval_value: 1,
            repetition_dow_value: [],
            repetition_dow_week_value: 0,
            repetition_month_type_value: '',
            repetition_month_value: 0,
            repetition_dom_value: 0,
            repetition_end_type_value: '',
            repetition_end_date_value: '',
            number_of_dates_value: 0
        };
    },
    mounted () {
        this.repetition_type_value = this.repetition_type;
        this.repetition_interval_value = this.repetition_interval;
        this.repetition_dow_value = this.repetition_dow;
        this.repetition_dow_week_value = this.repetition_dow_week;
        if (this.repetition_month_type === undefined) {
            this.repetition_month_type_value = this.repetition_dow.length > 0 ? 'dow' : 'dom';
        } else {
            this.repetition_month_type_value = this.repetition_month_type;
        }

        this.repetition_month_value = this.repetition_month;
        this.repetition_dom_value = this.repetition_dom;
        this.repetition_end_type_value = '';
        if (this.repetition_end_type !== undefined) {
            this.repetition_end_type_value = this.repetition_end_type;
        } else if (this.number_of_dates > 1) {
            this.repetition_end_type_value = 'end_count';
        } else if (this.repetition_end_date) {
            this.repetition_end_type_value = 'end_date';
        }
        this.repetition_end_date_value = this.repetition_end_date;
        this.number_of_dates_value = this.number_of_dates;
    },
    watch: {
        repetition_type_value(new_value) {
            this.$emit('input_repetition_type', new_value);
        },
        repetition_interval_value(new_value) {
            this.$emit('input_repetition_interval', new_value);
        },
        repetition_dow_value: {
            handler(new_value) {
                this.$emit('input_repetition_dow', new_value);
            },
            deep: true,
        },
        repetition_dow_week_value(new_value) {
            this.$emit('input_repetition_dow_week', new_value);
        },
        repetition_month_type_value(new_value) {
            this.$emit('input_repetition_month_type', new_value);
        },
        repetition_month_value(new_value) {
            this.$emit('input_repetition_month', new_value);
        },
        repetition_dom_value(new_value) {
            this.$emit('input_repetition_dom', new_value);
        },
        repetition_end_type_value(new_value) {
            this.$emit('input_repetition_end_type', new_value);
        },
        repetition_end_date_value(new_value) {
            this.$emit('input_repetition_end_date', new_value);
        },
        number_of_dates_value(new_value) {
            this.$emit('input_number_of_dates', new_value);
        }
    }
}
</script>
