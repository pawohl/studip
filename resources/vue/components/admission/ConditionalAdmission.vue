<template>
    <form class="default">
        <section>
            <label>
                {{ $gettext('Nachricht bei fehlgeschlagener Anmeldung') }}
                <textarea name="message" rows="4" cols="50" v-model="messageText"></textarea>
            </label>
        </section>
        <validity-time v-model:start="startTime"
                       v-model:end="endTime"
        />
        <section>
            <h3>
                {{ $gettext('Anmeldebedingungen') }}
            </h3>
            <div v-if="ungrouped?.length > 0"
                 role="list">
                <div v-for="(filter, index) in ungrouped"
                     :key="filter.attributes.provisional_id"
                     class="admission-condition"
                     role="listitem">
                    <p v-if="ungrouped.length > 1 && index >= 1">
                        {{ $gettext('oder') }}
                    </p>
                    <p v-if="!groupsAllowed"
                       class="condition-description"
                       v-html="filter.attributes.text"></p>
                    <label v-else class="undecorated">
                        <input type="checkbox"
                               v-model="selectedFilters"
                               :value="filter.attributes.provisional_id">
                        <span v-html="filter.attributes.text"></span>
                    </label>
                    <a @click.prevent="editFilter(index)"
                       :title="$gettext('Diese Bedingung bearbeiten')">
                        <studip-icon shape="edit"></studip-icon>
                    </a>
                    <a @click.prevent="deleteFilter(index)"
                       :title="$gettext('Diese Bedingung löschen')">
                        <studip-icon shape="trash"></studip-icon>
                    </a>
                </div>
                <button v-if="selectedFilters?.length > 0"
                        class="button"
                        @click.prevent="createContingent">
                    {{ $gettext('Kontingent erstellen') }}
                </button>
            </div>
            <div v-if="groups?.length > 0"
                 role="list">
                <h3>
                    {{ $gettext('Kontingente') }}
                </h3>
                <div v-for="(group, index) in groups"
                     :key="index"
                     class="admission-contingent"
                     role="listitem">
                    <div class="col-3">
                        <label>
                            {{ $gettext('Kontingent in Prozent') }}:
                            <input type="number"
                                   min="0"
                                   max="100"
                                   v-model="group.quota">
                        </label>
                        <ul>
                            <li v-for="(filter, fIndex) in group.conditions"
                                :key="fIndex">
                                <p v-html="filter.attributes.text"></p>
                            </li>
                        </ul>
                    </div>
                    <button type="button"
                            class="undecorated delete-contingent"
                            tabindex="0"
                            :title="$gettext('Kontingent auflösen')"
                            @click.prevent="deleteContingent(index)">
                        <studip-icon shape="trash"
                                     :size="20"></studip-icon>
                    </button>
                </div>
            </div>
            <p v-if="ungrouped?.length + groups?.length === 0">
                {{ $gettext('Sie haben noch keine Bedingungen festgelegt.') }}
            </p>
        </section>
        <section>
            <button class="button add"
                    @click.prevent="editFilter(null)">
                {{ $gettext('Bedingung hinzufügen') }}
            </button>
        </section>
        <studip-user-filter v-if="showEditFilter"
                            :filter="currentFilter"
                            @submit="confirmDialog"
                            @close="closeDialog"></studip-user-filter>
    </form>
</template>

<script>
import { AdmissionRuleMixin } from '../../mixins/AdmissionRuleMixin';
import ValidityTime from "./ValidityTime.vue";
import StudipUserFilter from "../StudipUserFilter.vue";

export default {
    name: 'ConditionalAdmission',
    components: { StudipUserFilter, ValidityTime},
    mixins: [AdmissionRuleMixin],
    data() {
        return {
            messageText: this.message || this.$gettext('Zur Anmeldung müssen diese Bedingungen erfüllt sein: %s'),
            ungrouped: [],
            groups: [],
            showEditFilter: false,
            currentFilterIndex: null,
            currentFilter: null,
            selectedFilters: [],
            startTime: 0,
            endTime: 0
        }
    },
    computed: {
        groupsAllowed() {
            return this.assignedRuleTypes.includes('ParticipantRestrictedAdmission')
        },
        payload() {
            return {
                type: 'ConditionalAdmission',
                payload: {
                    conditions: this.ungrouped,
                    'grouped-conditions': this.groups,
                    'conditiongroups-allowed': this.groupsAllowed,
                    message: this.message,
                    'start-time': this.startTime === 0 ? null : this.startTime,
                    'end-time': this.endTime === 0 ? null: this.endTime
                }
            }
        }
    },
    methods: {
        editFilter(index) {
            this.showEditFilter = true;
            this.currentFilterIndex = index;
            this.currentFilter = index === null ? [] : this.ungrouped[index].attributes.fields;
        },
        deleteFilter(index) {
            this.ungrouped.splice(index, 1);
        },
        closeDialog() {
            this.showEditFilter = false;
            this.currentFilterIndex = null;
            this.currentFilter = null;
        },
        confirmDialog(filter) {
            STUDIP.jsonapi.withPromises().post(
                'user-filters',
                {
                    data: {
                        data: {
                            attributes: {
                                filters: filter
                            }
                        }
                    }
                })
            .then(response => {
                if (this.currentFilterIndex !== null) {
                    this.ungrouped[this.currentFilterIndex] = response.data;
                } else {
                    this.ungrouped.push(response.data);
                }
                this.showEditFilter = false;
                this.currentFilterIndex = this.currentFilter = null;
            });
        },
        setRuleData(data) {
            this.messageText = data.attributes.payload['message'];
            this.ungrouped = data.attributes.payload['conditions'];
            this.groups = data.attributes.payload['grouped-conditions'];
            this.startTime = parseInt(data.attributes.payload['start-time'], 10);
            this.endTime = parseInt(data.attributes.payload['end-time'], 10);
        },
        validate() {
            if (this.ungrouped.length + this.groups.length === 0) {
                this.invalidData.push(this.$gettext('Bitte geben Sie mindestens eine Auswahlbedingung an.'));
            }

            return this.invalidData.length === 0;
        },
        createContingent() {
            let setQuotas = 100;
            this.groups.forEach(group => {
                setQuotas -= group.quota;
            });
            this.groups.push({
                id: null,
                quota: Math.max(setQuotas, 0),
                conditions: this.ungrouped.filter(element => {
                    return this.selectedFilters.includes(element.attributes.provisional_id);
                })
            });
            this.ungrouped = this.ungrouped.filter(element => {
                return !this.selectedFilters.includes(element.attributes.provisional_id);
            });
            this.selectedFilters = [];
        },
        deleteContingent(index) {
            this.groups[index].conditions.forEach(filter => {
                this.ungrouped.push(filter);
            });
            this.groups.splice(index, 1);
        }
    }
}
</script>

<style lang="scss" scoped>
.delete-contingent {
    margin-top: 2ex;
}
</style>
