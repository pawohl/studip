<template>
    <div class="formpart">
        <label v-if="with_semester_selector">
            {{ $gettext('Semester') }}
            <select :name="`${name}_semester_id`" v-model="semester_id">
                <option v-for="semester in available_semesters"
                        :value="semester.id"
                        :key="semester.id"
                >
                    {{ semester.name }}
                </option>
            </select>
        </label>

        <table class="default mycourses">
            <caption>{{ semesterName }}</caption>
            <colgroup>
                <col style="width: 7px">
                <col style="width: 25px">
                <col style="width: 70px">
                <col>
                <col>
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th></th>
                    <th>{{ $gettext('Nummer') }}</th>
                    <th>{{ $gettext('Name') }}</th>
                    <th class="actions">{{ $gettext('Auswahl') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="course of courses" :key="course.id">
                    <td :class="`gruppe${course.group}`"></td>
                    <td>
                        <img :src="course.avatar_url" alt="" class="my-courses-avatar course-avatar-small">
                    </td>
                    <td>{{ course.number }}</td>
                    <td>{{ course.name }}</td>
                    <td class="actions">
                        <input type="hidden" :name="`${name}_course_ids[${course.id}]`" value="0">
                        <input type="checkbox" :name="`${name}_course_ids[${course.id}]`"
                               value="1" :checked="selected_course_id_list.includes(course.id)"
                               :title="$gettext('%{course} auswählen', {course: course.name}, true)">
                    </td>
                </tr>
                <tr v-if="loadedSemesters.includes(semester_id) && courses.length === 0">
                    <td colspan="5">
                        <studip-message-box>{{ $gettext('Im gewählten Semester stehen keine Veranstaltungen zur Auswahl zur Verfügung.') }}</studip-message-box>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import StudipMessageBox from "../StudipMessageBox.vue";

export default {
    name: 'my-courses-coloured-table',
    components: {StudipMessageBox},
    props: {
        default_semester_id: {
            type: String,
            required: true,
        },
        selected_course_ids: {
            type: Array,
            required: false,
            default: () => [],
        },
        name: {
            type: String,
            required: false,
            default: 'selected_course_ids',
        },
        semester_data: {
            type: Object,
            required: false,
            default: () => {},
        }
    },
    data() {
        //Retrieve all semesters, if the semester selector is present:
        let semester_data = this.semester_data;
        return {
            available_semesters: semester_data,
            semester_id: null,
            semester_courses: Object.values(semester_data).reduce(
                (carry, current) => {
                    carry[current.id] = [];
                    return carry;
                },
                {}
            ),
            selected_course_id_list: [...this.selected_course_ids],
            with_semester_selector: Object.keys(semester_data).length > 0,
            membershipGroups: {},
            loadedSemesters: [],
        };
    },
    created() {
        this.semester_id = this.default_semester_id;

        STUDIP.jsonapi.GET(`users/${STUDIP.USER_ID}/course-memberships`, {
            data: {
                'page[limit]': 1000,
            }
        }).done((response) => {
            this.membershipGroups = Object.values(response.data).reduce(
                (carry, current) => {
                    carry[current.id.split('_')[0]] = current.attributes.group;
                    return carry;
                },
                {}
            );
        })
    },
    methods: {
        loadSemesterCourses(semester_id) {
            if (this.loadedSemesters.includes(semester_id)) {
                return;
            }

            // The courses have not yet been retrieved.
            STUDIP.jsonapi.GET(`users/${STUDIP.USER_ID}/courses`, {
                data: {
                    'fields[courses]': 'id,course-number,title,course-type',
                    'filter[semester]': semester_id,
                    'include': 'memberships',
                    'page[limit]': 1000,
                }
            }).done((response) => {
                this.semester_courses[semester_id] = response.data
                    .filter(item => item.type === 'courses')
                    .map(item => ({
                        id: item.id,
                        name: item.attributes.title,
                        number: item.attributes['course-number'] ?? '',
                        group: this.membershipGroups[item.id] ?? item.attributes['course-type'],
                        avatar_url: item.meta.avatar.small,
                    }));

                this.loadedSemesters.push(semester_id);
            });
        }
    },
    computed: {
        courses() {
            return [...this.semester_courses[this.semester_id]].sort((a, b) => {
                return a.name.localeCompare(b.name)
                    || a.number.localeCompare(b.number);
            });
        },
        semesterName() {
            return this.available_semesters[this.semester_id].name ?? '';
        },
    },
    watch: {
        semester_id(current) {
            this.loadSemesterCourses(current);
        }
    }
}
</script>
