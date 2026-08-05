<template>
    <div id="my_seminars">
        <table class="default collapsable mycourses" v-for="group in groups" :key="group.id">
            <caption> {{ group.name }}</caption>
            <colgroup>
                <col style="width: 7px">
                <col style="width: 25px">
                <col style="width: 70px" v-if="displaySemNumberSeparate">
                <col>
                <col v-if="!responsiveDisplay" :style="{width: (2 * 5 + numberOfNavElements * (iconSize + 2 * 3 + 3) - 3) + 'px'}">
                <col v-if="!responsiveDisplay" style="width: 24px">
            </colgroup>
            <thead>
                <tr class="sortable">
                    <th>
                        <span class="sr-only">
                            {{ $gettext('Zugeordnete Farbgruppe') }}
                        </span>
                    </th>
                    <th></th>
                    <th v-if="displaySemNumberSeparate" :class="getOrderClasses('number')">
                        <a href="#" @click.prevent="changeOrder('number')">
                            {{ $gettext('Nr.') }}
                        </a>
                    </th>
                    <th :class="getOrderClasses('name')">
                        <a href="#" @click.prevent="changeOrder('name')">
                            {{ $gettext('Name') }}
                        </a>
                    </th>
                    <th v-if="!responsiveDisplay" >{{ $gettext('Inhalt') }}</th>
                    <th v-if="!responsiveDisplay"></th>
                </tr>
            </thead>
            <tbody v-for="subgroup in group.data" :key="subgroup.id" :class="{collapsed: !isGroupOpen(subgroup)}">
                <tr class="header-row" v-if="subgroup.label !== false">
                    <th style="white-space: nowrap; text-align: left"></th>
                    <th class="toggle-indicator" :colspan="displaySemNumber ? 3 : 2">
                        <a href="#" @click.prevent.stop="toggleOpenGroup(subgroup)">{{ subgroup.label }}</a>
                    </th>
                    <th v-if="!responsiveDisplay" class="dont-hide" colspan="2"></th>
                </tr>
                <tr v-for="course in getOrderedCourses(subgroup.ids)" :data-course-id="course.id" :class="getCourseClasses(course)" :key="course.id">
                    <td :class="`gruppe${course.group}`">
                        <span class="sr-only">
                            {{ $gettext(
                                'Diese Veranstaltung gehört zur Farbgruppe %{group}',
                                course
                            ) }}
                        </span>
                    </td>
                    <td :class="{'subcourse-indented': isChild(course)}">
                        <span :style="{backgroundImage: `url(${course.avatar}`}" class="my-courses-avatar course-avatar-small" :title="course.name" alt=""></span>
                    </td>
                    <td v-if="displaySemNumberSeparate"  :class="{'subcourse-indented': isChild(course)}">
                        {{ course.number }}
                    </td>
                    <td :class="{'subcourse-indented': isChild(course)}">
                        <a :href="getCourseURL(course)">
                            {{ getCourseName(course) }}
                        </a>
                        <span v-if="course.is_hidden" class="course-hidden-info">
                            {{ $gettext('[versteckt]') }}
                            <studip-tooltip-icon :text="getHiddenTooltip(course)"></studip-tooltip-icon>
                        </span>
                        <div v-if="responsiveDisplay" class="mycourse_elements">
                            <div class="special_nav">
                                <studip-action-menu :items="getActionMenuForCourse(course)"
                                                    :collapseAt="false"
                                                    v-on:show-color-picker="shownColorPicker = course.id"
                                ></studip-action-menu>
                            </div>

                            <navigation :navigation="getNavigationForCourse(course)" :icon-size="iconSize"></navigation>
                        </div>
                    </td>
                    <td v-if="!responsiveDisplay" class="course-navigation">
                        <navigation :navigation="getNavigationForCourse(course, true)" :icon-size="iconSize"></navigation>
                    </td>
                    <td v-if="!responsiveDisplay" class="actions">
                        <studip-action-menu :items="getActionMenuForCourse(course)"></studip-action-menu>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import MyCoursesMixin from '../../mixins/MyCoursesMixin.js';

const defaultIconSize = parseInt(
    getComputedStyle(document.body).getPropertyValue('--icon-size-default'),
    10
);

export default {
    name: 'TableView',
    mixins: [MyCoursesMixin],
    props: {
        iconSize: {
            type: Number,
            required: false,
            default: defaultIconSize
        }
    },
    data () {
        return {
            orderBy: 'group',
            orderDir: 'asc'
        }
    },
    methods: {
        changeOrder (by) {
            if (this.orderBy === by) {
                this.orderDir = this.orderDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.orderBy = by;
                this.orderDir = 'asc';
            }
        },
        getCourseClasses (course) {
            return {
                'has-subcourses': this.isParent(course),
                subcourses: this.isChild(course),
            };
        },
        getOrderedCourses (ids) {
            const sorted = this.getCourses(ids);
            const dirFactor = this.orderDir === 'desc' ? -1 : 1;
            if (this.orderBy === 'name') {
                sorted.sort((a, b) => a.name.localeCompare(b.name) * dirFactor);
            } else if (this.orderBy === 'number') {
                sorted.sort((a, b) => a.number.localeCompare(b.number) * dirFactor);
            }

            // Ensure parent / child relation
            let courses = [];
            sorted.forEach(course => {
                courses.push(course);
            });

            return courses;
        },
        getOrderClasses (by) {
            if (by !== this.orderBy) {
                return [];
            }
            return this.orderDir === 'asc' ? ['sortasc'] : ['sortdesc'];
        }
    }
}
</script>
