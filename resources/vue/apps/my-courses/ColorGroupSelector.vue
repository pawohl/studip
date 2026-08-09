<template>
    <form method="post" :action="storeUrl" class="default" @submit="secured = false">
        <input type="hidden" :name="csrf.name" :value="csrf.value">
        <input v-if="cid"
               type="hidden"
               name="cid"
               :value="cid"
        >
        <input v-for="(group, id) in courseGroups"
               :key="`input-${id}`"
               type="hidden"
               :name="`gruppe[${id}]`"
               :value="group"
        >

        <table class="default">
            <caption>{{ $gettext('Gruppenzuordnung') }}</caption>
            <colgroup>
                <col>
                <col v-for="i in maxgroups"
                     :key="`col-${i}`"
                     style="width: 34px"
                >
            </colgroup>
            <thead>
                <tr>
                    <th>{{ $gettext('Veranstaltung') }}</th>
                    <th :colspan="maxgroups">{{ $gettext('Gruppe/Farbe') }}</th>
                </tr>
            </thead>
            <tbody v-for="group in groups"
                   :key="`group-${group.id}`"
            >
                <tr>
                    <th>{{ group.name }}</th>
                    <th v-for="i in maxgroups" :key="`group-label-${group.id}-${i}`">
                        {{ i }}
                    </th>
                </tr>
                <tr v-for="course in getCoursesForGroup(group)"
                    :key="`course-${group.id}-${course.id}`"
                    role="radiogroup"
                    :aria-label="$gettext('Gruppenauswahl für Veranstaltung %{name}', course)"
                >
                    <td>
                        <a :href="getCourseURL(course)">
                            {{ getCourseName(course) }}
                            <template v-if="course.is_hidden">
                                {{ $gettext('(versteckt)') }}
                            </template>
                        </a>
                    </td>
                    <td v-for="(i, index) in maxgroups"
                        class="colour-selector mycourses-group-selector"
                        :key="`selector-${group.id}-${course.id}-${i}`"
                        @mouseover="hovered = {id: course.id, index}"
                        @mouseleave="hovered = false"
                    >
                        <input type="radio"
                               :name="`gruppe[${course.id}]`"
                               :id="`course-${course.id}-group-${i}`"
                               :value="index"
                               :aria-label="$gettext('Gruppe %{i} zuordnen', {i})"
                               v-model="courseGroups[course.id]"
                        >
                        <label :for="`course-${course.id}-group-${i}`"
                               :class="`gruppe${index}`"
                        >
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <footer data-dialog-button style="text-align: center">
            <button type="submit" class="button accept">
                {{ $gettext('Speichern') }}
            </button>
            <button v-if="dialog"
                    class="button cancel"
                    type="button"
                    @click.prevent="closeDialog()"
            >
                {{ $gettext('Abbrechen') }}
            </button>
        </footer>
    </form>
</template>
<script>
import { mapState } from "vuex";
import { createMixin } from "@/vue/mixins/MyCoursesMixin";
import {useSecurityHandler} from "@/vue/composables/useSecurityHandler";

export default {
    name: "MyCoursesColorGroupSelector",
    mixins: [
        createMixin(),
    ],
    props: {
        cid: String,
        maxgroups: {
            type: Number,
            default: 9
        },
        storeUrl: String,
    },
    setup() {
        return {
            securityHandler: useSecurityHandler()
        };
    },
    data() {
        return {
            courseGroups: [],
            dialog: null,
            hovered: false,
            inDialog: false,
            secured: true,
        };
    },
    computed: {
        ...mapState('mycourses', [
            'courses',
            'groups',
            'config',
        ]),

        isChanged() {
            return Object.entries(this.courses).some(([id, course]) => {
                return this.courseGroups[id] !== course.group;
            });
        }
    },
    methods: {
        closeDialog() {
            STUDIP.Dialog.close();
        },
        getCoursesForGroup(group) {
            return group.data.map(item => item.ids).flat().map(id => this.courses[id]);
        },

        securityHandlerDialog(event) {
            if (
                !this.isChanged
                || !this.secured
                || window.confirm(this.$gettext('Ihre Eingaben wurden bislang noch nicht gespeichert.'))
            ) {
                return true;
            }

            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    },
    created() {
        this.securityHandler.setPredicate(() => this.isChanged && this.secured);

        this.courseGroups = Object.values(this.courses).reduce(
            (all, course) => {
                all[course.id] = course.group;
                return all;
            },
            {}
        );
    },
    mounted() {
        this.dialog = this.$el?.closest('.studip-dialog');

        if (this.dialog !== null) {
            this.securityHandler.deactivate();

            $(this.dialog).on('dialogbeforeclose', this.securityHandlerDialog);

            this.$nextTick(() => {
                this.dialog.querySelector('.ui-dialog-content input[type="radio"]:checked')?.focus();
            });
        }
    },
    beforeUnmount() {
        if (this.dialog !== null) {
            $(this.dialog).off('dialogbeforeclose', this.securityHandlerDialog);
        }
    }
}
</script>
<style lang="scss" scoped>
table.default {
    th,
    td {
        padding-right: 0;
    }
    tbody th:not(:first-child) {
        text-align: center;
    }
}
</style>
