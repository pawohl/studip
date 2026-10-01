<template>
    <form class="default">
        <section>
            <label>
                {{ $gettext('Nachricht bei fehlgeschlagener Anmeldung') }}
                <textarea name="message" rows="4" cols="50" v-model="messageText"></textarea>
            </label>
        </section>
        <section class="col-3">
            <label>
                {{ $gettext('Start des Anmeldezeitraums') }}
                <datetimepicker v-model="startTime"></datetimepicker>
            </label>
        </section>
        <section class="col-3">
            <label>
                {{ $gettext('Ende des Anmeldezeitraums') }}
                <datetimepicker v-model="endTime"></datetimepicker>
            </label>
        </section>
    </form>
</template>

<script>
import { AdmissionRuleMixin } from '../../mixins/AdmissionRuleMixin';
import datetimepicker from "../Datetimepicker.vue";
export default {
    name: 'TimedAdmission',
    components: { datetimepicker },
    mixins: [ AdmissionRuleMixin ],
    props: {
        start: {
            type: Number,
            default: null
        },
        end: {
            type: Number,
            default: null
        },
        message: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            messageText: this.message || this.$gettext('Die Anmeldung ist nur innerhalb des angegebenen Zeitraums möglich.'),
            startTime: this.start,
            endTime: this.end,
        }
    },
    computed: {
        payload() {
            return {
                type: 'TimedAdmission',
                payload: {
                    'starttime': this.startTime,
                    'endtime': this.endTime,
                    message: this.messageText
                }
            }
        }
    },
    methods: {
        setRuleData(data) {
            this.messageText = data.attributes.payload['message'];
            this.startTime = data.attributes.payload['starttime'];
            this.endTime = data.attributes.payload['endtime'];
        },
        validate() {
            if (this.startTime && this.endTime && this.endTime <= this.startTime) {
                this.invalidData.push(this.$gettext('Die Endzeit muss nach der Startzeit liegen.'));
            }
            if (!this.startTime && !this.endTime) {
                this.invalidData.push(this.$gettext('Bitte geben Sie entweder ein Start- oder Enddatum an.'));
            }

            return this.invalidData.length === 0;
        }
    }
}
</script>
