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
            <label>
                <input type="radio" v-model="theMode" :value="0">
                {{ $gettext('Mitgliedschaft ist in mindestens einer dieser Veranstaltungen notwendig') }}
            </label>
            <label>
                <input type="radio" v-model="theMode" :value="1">
                {{ $gettext('Mitgliedschaft ist in keiner dieser Veranstaltungen erlaubt') }}
            </label>
        </section>
        <section class="col-4">
            <label>
                {{ $gettext('Veranstaltung(en)') }}
                <quicksearch v-if="courseSearch !== null"
                             :searchtype="courseSearch"
                             name="course"
                             @update:model-value="addCourse"
                             ref="courseSearch"></quicksearch>
            </label>
        </section>
        <section class="col-2">
            <label>
                {{ $gettext('Semester') }}
                <span class="admission-aligned">
                    <select
                        name="search_sem_sem"
                        v-model="searchSemester"
                    >
                        <option
                            v-for="semester in semesters"
                            :key="semester.id"
                            :value="semester.id"
                        >
                            {{ semester.attributes.title }}
                        </option>
                    </select>
                </span>
            </label>
        </section>
        <section>
            <ul v-if="courseList.length > 0">
                <li v-for="(course, index) in courseList" :key="index">
                    {{ course.name }}
                    <a
                        href="#"
                        :title="deleteLinkText(index)"
                        @click.prevent="removeCourse(index)"
                    >
                        <studip-icon shape="trash" />
                    </a>
                </li>
            </ul>
        </section>
    </form>
</template>

<script>
import {AdmissionRuleMixin} from '../../mixins/AdmissionRuleMixin';
import ValidityTime from './ValidityTime.vue';
import quicksearch from '../Quicksearch.vue';

export default {
    name: 'CourseMemberAdmission',
    components: { ValidityTime, quicksearch },
    mixins: [AdmissionRuleMixin],
    data() {
        return {
            messageText: this.message || (
                this.theMode === 0
                    ? this.$gettext('Sie sind nicht in einer der gewählten Veranstaltungen eingetragen.')
                    : this.$gettext('Sie sind bereits in einer der gewählten Veranstaltungen eingetragen.')
            ),
            theMode: 0,
            courseList: [],
            courseSearch: null,
            startTime: 0,
            endTime: 0,
            semesters: [],
            searchSemester: ''
        }
    },
    computed: {
        payload() {
            return {
                type: 'CourseMemberAdmission',
                payload: {
                    modus: this.theMode,
                    courses: this.courseList,
                    message: this.messageText,
                    'start-time': this.startTime === 0 ? null : this.startTime,
                    'end-time': this.endTime === 0 ? null: this.endTime
                }
            }
        }
    },
    methods: {
        addCourse(returnValue, inputValue) {
            if (!this.courseList.some(i => i.id === returnValue)) {
                this.courseList.push({id: returnValue, name: inputValue});
            }
        },
        setRuleData(data) {
            this.messageText = data.attributes.payload['message'];
            this.courseSearch = data.attributes.payload.search;
            this.courseList = data.attributes.payload.courses;
            this.theMode = data.attributes.payload.modus;
            this.startTime = parseInt(data.attributes.payload['start-time'], 10);
            this.endTime = parseInt(data.attributes.payload['end-time'], 10);
        },
        validate() {
            if (this.courseList.length === 0) {
                this.invalidData.push(this.$gettext('Bitte geben Sie mindestens eine Veranstaltung an.'));
            }

            return this.invalidData.length === 0;
        },
        removeCourse(index) {
            this.courseList.splice(index, 1);
        },
        deleteLinkText(index) {
            return this.$gettext('Veranstaltung %{course} entfernen', {course: this.courseList[index].name});
        }
    },
    created() {
        // Get a new rule instance so we can use quicksearch.
        if (!this.id || this.id === '') {
            STUDIP.jsonapi.withPromises().post('admission-rules/CourseMemberAdmission', {
                data: {
                    data: {
                        attributes: {
                            payload: {
                                modus: 0,
                                courses: [],
                                message: ''
                            }
                        }
                    }
                }
            }).then(response => {
                this.courseSearch = response.data.attributes.payload.search;
            });
        }
        STUDIP.jsonapi.withPromises().get('semesters')
            .then(response => {
                this.semesters = response.data;
                this.searchSemester = response.data.filter(semester => semester.attributes['is-current']).at(0)?.id;
            });
    },
}
</script>

<style lang="scss" scoped>
.admission-aligned {
    display: inline-block;
    margin-top: 15px;
    width: 100%;
}
</style>
