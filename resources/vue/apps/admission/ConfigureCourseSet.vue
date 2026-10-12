<template>
    <div>
        <form class="default"
              :action="storeUrl"
              method="post"
              ref="courseSetForm"
        >
            <fieldset>
                <legend>{{ $gettext('Grunddaten') }}</legend>
                <section>
                    <label class="studiprequired">
                        <span class="textlabel">
                            {{ $gettext('Name des Anmeldesets') }}
                        </span>
                        <span class="asterisk" :title="$gettext('Dies ist ein Pflichtfeld')" aria-hidden="true">*</span>
                        <input type="text"
                               name="name"
                               maxlength="255"
                               v-model="name">
                    </label>
                </section>
                <section v-if="!instantCourseSetView">
                    <label for="private"
                           :aria-label="$gettext('Dieses Anmeldeset soll nur für mich selbst und alle Administratoren sichtbar und benutzbar sein.')">
                        {{ $gettext('Sichtbarkeit') }}
                    </label>
                    <input type="checkbox"
                           name="private"
                           id="private"
                           v-model="private">
                    {{ $gettext('Dieses Anmeldeset soll nur für mich selbst und alle Administratoren sichtbar und benutzbar sein.') }}
                </section>
            </fieldset>
            <fieldset v-if="!instantCourseSetView">
                <legend>{{ $gettext('Einrichtungszuordnung') }}</legend>
                <section v-if="instituteSearch || myInstitutes?.length > 1">
                    <label for="isearch" class="studiprequired">
                        <span class="textlabel">
                            {{ $gettext('Einrichtung wählen') }}
                        </span>
                        <span class="asterisk" :title="$gettext('Dies ist ein Pflichtfeld')" aria-hidden="true">*</span>
                    </label>
                    <quicksearch v-if="instituteSearch"
                                 :searchtype="instituteSearch"
                                 name="institute"
                                 id="isearch"
                                 @input="addInstitute"
                                 :aria-label="$gettext('Geben Sie einen Suchbegriff mit mehr als 3 Zeichen ein, um nach Einrichtungen zu suchen')"
                                 ref="instituteSearch"></quicksearch>
                    <select v-if="myInstitutes?.length > 1"
                            name="institute"
                            id="isearch"
                            @change.prevent="setInstitute">
                        <option value="">-- {{ $gettext('bitte wählen') }} --</option>
                        <option v-for="institute in myInstitutes"
                                :key="institute.id"
                                :value="institute.id"
                        >
                            {{ institute.name }}
                        </option>
                    </select>
                </section>
                <section>
                    <header>
                        <h2>{{ $gettext('Bereits zugeordnet') }}</h2>
                    </header>
                    <table v-if="institutes?.length > 0" class="default assignments">
                        <tbody>
                            <tr v-for="(institute, index) in institutes"
                                :key="institute.id"
                                class="institute-assignment">
                                <td>
                                    <input type="hidden"
                                           name="institutes[]"
                                           :value="institute.id">
                                    {{ institute.name }}
                                </td>
                                <td class="actions">
                                    <button v-if="myInstitutes?.length !== 1"
                                            :title="$gettext(
                                                'Zuordnung der Einrichtung %{name} entfernen',
                                                { name: institute.name }
                                            )"
                                            :aria-label="$gettext(
                                                'Zuordnung der Einrichtung %{name} entfernen',
                                                { name: institute.name }
                                            )"
                                            class="as-link delete-assignment"
                                            tabindex="0"
                                            @click.prevent="removeInstitute(index)">
                                        <studip-icon shape="trash"></studip-icon>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else>
                        {{ $gettext('Aktuell sind keine Einrichtungen zugeordnet.') }}
                    </p>
                </section>
            </fieldset>
            <fieldset v-if="!instantCourseSetView && (institutes?.length > 0 || courses?.length > 0)">
                <legend>
                    {{ $gettext('Veranstaltungszuordnung') }}
                </legend>
                <section>
                        <label class="col-2">
                            {{ $gettext('Semester') }}
                            <select ref="semesterChooser"
                                    v-model="selectedSemester"
                                    @change.prevent="getAvailableCourses">
                                <option v-for="semester in allSemesters"
                                        :key="semester.id"
                                        :value="semester.id">
                                    {{ semester.name }}
                                </option>
                            </select>
                        </label>
                        <label class="col-3">
                            {{ $gettext('Suche nach Titel, Nummer, Lehrenden') }}
                            <studip-tooltip-icon :text="$gettext('Geben Sie einen Suchbegriff mit mehr als 3 '
                                + 'Zeichen an oder lassen Sie das Feld leer, um nach allem zu suchen.')"/>
                            <input type="text"
                                   v-model="courseSearchterm"
                                   @keydown.enter.prevent="getAvailableCourses"
                                   ref="courseSearch"/>
                        </label>
                        <button class="button search-button"
                                :disabled="!canSearchCourses"
                                @click.prevent="getAvailableCourses">
                            {{ $gettext('Suche') }}
                        </button>
                </section>
                <section>
                    <template v-if="!isSearching">
                        <table v-if="availableCourses?.length > 0"
                               class="default">
                            <caption>
                                {{ $gettext(
                                    'Veranstaltungen im %{semester}',
                                    { semester: allSemesters[selectedSemester].name }
                                ) }}
                            </caption>
                            <colgroup>
                                <col style="width: 15px">
                                <col>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th colspan="2">
                                        <input type="checkbox"
                                               :checked="allCoursesChecked"
                                               @click="checkUncheckAll"
                                               :title="$gettext('alle (ab)wählen')"
                                               ref="proxy"
                                        >
                                        {{ $gettext('Veranstaltung') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="course in availableCourses" :key="course.id">
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   :value="course.id"
                                                   class="assignable-course"
                                                   v-model="checkedCourses"
                                                   :title="$gettext(
                                                       'Veranstaltung %{coursename} dem Anmeldeset zuordnen',
                                                       { coursename: course.attributes.title }
                                                   )">
                                            <template v-if="course.attributes['course-number']">
                                                {{ course.attributes['course-number'] }}
                                            </template>
                                            {{ course.attributes.title }}
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <studip-message-box v-if="!isSearching && noCoursesFound"
                                            type="info"
                                            :hide-close="true"
                                            role="alert">
                            {{ $gettext('Es wurden keine Veranstaltungen gefunden, die zugeordnet werden könnten.') }}
                        </studip-message-box>
                    </template>
                    <studip-progress-indicator v-else :size="32"
                                               :description="$gettext('Veranstaltungen werden gesucht...')" />
                </section>
                <table v-if="courses?.length > 0"
                       class="default assignments">
                    <caption>{{ $gettext('Bereits zugeordnet') }}</caption>
                    <thead>
                    </thead>
                    <tbody>
                        <tr v-for="(course, index) in courses"
                            :key="course.id"
                            class="course-assignment"
                        >
                            <td>
                                <template v-if="course.attributes['course-number']">
                                    {{ course.attributes['course-number'] }}
                                </template>
                                {{ course.attributes.title }}
                            </td>
                            <td class="actions">
                                <button :title="$gettext(
                                            'Zuordnung der Veranstaltung %{name} entfernen',
                                            { name: course.attributes.title }
                                        )"
                                        :aria-label="$gettext(
                                            'Zuordnung der Veranstaltung %{name} entfernen',
                                            { name: course.attributes.title }
                                        )"
                                        class="as-link delete-assignment"
                                        tabindex="0"
                                        @click.prevent="removeCourse(index)">
                                    <studip-icon shape="trash"></studip-icon>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button v-if="hasConfigurableCourses"
                        class="button"
                        @click.prevent="configureCourses">
                    {{ $gettext('Veranstaltungen konfigurieren') }}
                </button>
                <button v-if="numApplicants > 0"
                        class="button"
                        @click.prevent="getApplicants">
                    {{ $gettext(
                        'Liste der Anmeldungen (%{number} Personen)',
                        { number: numApplicants }
                    ) }}
                </button>
                <button v-if="numApplicants > 0"
                        class="button"
                        @click.prevent="messageApplicants">
                    {{ $gettext('Nachricht an alle Angemeldeten') }}
                </button>
            </fieldset>
            <fieldset>
                <legend class="studiprequired">
                    <span class="textlabel">
                        {{ $gettext('Anmelderegeln') }}
                    </span>
                    <span class="asterisk" :title="$gettext('Dies ist ein Pflichtfeld')" aria-hidden="true">*</span>
                </legend>
                <section>
                    <table v-if="rules.length > 0" class="default assignments">
                        <tbody>
                            <tr v-for="(rule, index) in rules"
                                :key="index"
                                class="rule-assignment"
                            >
                                <td v-html="rule.attributes.ruletext"></td>
                                <td class="actions">
                                    <button :title="$gettext(
                                                'Regel %{name} bearbeiten',
                                                { name: rule.attributes.name }
                                            )"
                                            :aria-label="$gettext(
                                                'Regel %{name} bearbeiten',
                                                { name: rule.attributes.name }
                                            )"
                                            class="as-link edit-assignment"
                                            tabindex="0"
                                            @click.prevent="configureRule(rule.attributes.type, rule, index)">
                                        <studip-icon shape="edit" :size="16"></studip-icon>
                                    </button>
                                    <button :title="$gettext(
                                                'Regel %{name} entfernen',
                                                { name: rule.attributes.name }
                                            )"
                                            :aria-label="$gettext(
                                                'Regel %{name} entfernen',
                                                { name: rule.attributes.name }
                                            )"
                                            class="as-link delete-assignment"
                                            tabindex="0"
                                            data-confirm="$gettext('Soll die Regel wirklich entfernt werden?')"
                                            @click.prevent="removeRule(index)">
                                        <studip-icon shape="trash"></studip-icon>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button class="button add add-rule-button"
                            type="button"
                            @click="addRule"
                    >
                        {{ $gettext('Anmelderegel hinzufügen') }}
                    </button>
                </section>
            </fieldset>
            <fieldset>
                <legend>
                    {{ $gettext('Weitere Daten') }}
                </legend>
                <section v-if="hasUserLists">
                    <label>
                        {{ $gettext('Personen mit Bonus/Malus bei der Platzverteilung') }}
                    </label>
                    <label v-for="list in myUserLists" :key="list.id">
                        <input type="checkbox" :value="list.id" v-model="userLists">
                        {{ list.name }}
                        ({{ userListText(list.factor, list.count) }})
                    </label>
                    <button v-if="showUserListUsers"
                            class="button"
                            @click.prevent="openUserListUsers">
                        {{ $gettext('Liste der Personen') }}
                    </button>
                </section>
                <section>
                    <label>
                        {{ $gettext('Weitere Hinweise für die Teilnehmenden') }}
                        <textarea name="infotext"
                                  cols="60"
                                  rows="3"
                                  v-model="additional"></textarea>
                    </label>
                </section>
            </fieldset>
            <footer data-dialog-button>
                <button class="button accept"
                        type="submit"
                        @click.prevent="storeCourseset"
                        :disabled="!isStorable">
                    {{ $gettext('Speichern') }}
                </button>
                <button class="button cancel"
                        type="button"
                        data-dialog-close
                        @click.prevent="cancel"
                >
                    {{ $gettext('Abbrechen') }}
                </button>
            </footer>
        </form>
        <admission-rule-type-selector v-if="showRuleSelector"
                                      :assigned-rule-types="ruleTypes"
                                      @configureRule="configureRule"
                                      @close="closeRuleSelector"
        ></admission-rule-type-selector>
        <admission-rule-config v-if="showRuleConfig && ruleType !== ''"
                               :type="ruleType"
                               :rule="singleRule"
                               :assigned-rule-types="ruleTypes"
                               @submit="addRuleConfiguration"
                               @cancel="closeRuleConfig"
        ></admission-rule-config>
    </div>
</template>

<script>
import quicksearch from '@/vue/components/Quicksearch.vue';
import AdmissionRuleTypeSelector from '@/vue/components/admission/AdmissionRuleTypeSelector.vue';
import AdmissionRuleConfig from '@/vue/components/admission/AdmissionRuleConfig.vue';
import StudipProgressIndicator from "@/vue/components/StudipProgressIndicator.vue";
import StudipTooltipIcon from '@/vue/components/StudipTooltipIcon.vue';
import { useSecurityHandler } from '@/vue/composables/useSecurityHandler';

export default {
    name: 'ConfigureCourseSet',
    components: { StudipTooltipIcon, StudipProgressIndicator, AdmissionRuleConfig, quicksearch, AdmissionRuleTypeSelector },
    props: {
        courseSetId: {
            type: String,
            default: ''
        },
        allSemesters: {
            type: Object,
            required: true
        },
        semester: {
            type: String,
            default: ''
        },
        instituteSearch: {
            type: String,
            default: ''
        },
        myInstitutes: {
            type: Array,
            default: () => []
        },
        myUserLists: {
            type: Array,
            default: () => []
        },
        instantCourseSetView: {
            type: Boolean,
            default: false
        }
    },
    setup() {
        return {
            securityHandler: useSecurityHandler()
        };
    },
    data() {
        return {
            name: '',
            private: true,
            numApplicants: 0,
            institutes: [],
            selectedSemester: this.semester,
            courseSearchterm: '',
            availableCourses: [],
            isSearching: false,
            noCoursesFound: false,
            checkedCourses: [],
            courses: [],
            rules: [],
            userLists: [],
            hasUserLists: false,
            additional: '',
            showRuleSelector: false,
            ruleType: '',
            ruleId: '',
            singleRule: null,
            ruleIndex: null,
            showRuleConfig: false,
            initialCoursesetDataAsJSON: null
        }
    },
    computed: {
        changed() {
            return this.initialCoursesetDataAsJSON !== null
                && JSON.stringify(this.coursesetData) !== this.initialCoursesetDataAsJSON;

        },
        isStorable() {
            return this.name !== ''
                && (this.courseSetId !== '' || this.courseSetId === '' && this.institutes.length > 0)
                && this.rules.length > 0;
        },
        hasConfigurableCourses() {
            return this.courseSetId
                && this.courseSetId !== ''
                && this.courses?.length > 0;
        },
        storeUrl() {
            let url = STUDIP.URLHelper.getURL('dispatch.php/admission/courseset/save', {}, true);

            if (this.courseSetId !== null) {
                url += '/' + this.courseSetId;
            }

            return url;
        },
        ruleTypes() {
            return this.rules.map(r => r.attributes.type);
        },
        canSearchCourses() {
            return !this.courseSearchterm
                || this.courseSearchterm === ''
                || this.courseSearchterm.trim().length >= 3
        },
        showUserListUsers() {
            return this.courseSetId !== '' && this.hasUserLists && this.userLists.length > 0;
        },
        allCoursesChecked() {
            return this.checkedCourses.length === this.availableCourses.length;
        },
        coursesetData() {
            return {
                name: this.name,
                private: this.private,
                infotext: this.additional,
                institutes: this.institutes.map(i => i.id),
                courses: this.courses.map(c => c.id).concat(this.checkedCourses),
                rules: this.rules,
                userlists: this.hasUserLists ? this.userLists : []
            };
        }
    },
    methods: {
        getSelectedSemester() {
            return this.allSemesters[this.selectedSemester];
        },
        getAvailableCourses() {
            if (this.canSearchCourses) {
                this.noCoursesFound = false;
                this.isSearching = true;
                this.courses = this.courses.concat(this.availableCourses.filter(c => this.checkedCourses.includes(c.id)));
                this.availableCourses = [];
                this.checkedCourses = [];

                let term = this.courseSearchterm;
                if (!this.courseSearchterm || this.courseSearchterm === '') {
                    term = '%%%';
                }

                STUDIP.jsonapi.withPromises().post(
                    'admission/available-courses',
                    {
                        data: {
                            institutes: this.institutes.map(i => {
                                return i.id;
                            }),
                            courseset: this.courseSetId ? this.courseSetId : null,
                            exclude: this.courses.map(course => course.id),
                            semester: this.selectedSemester,
                            filter: term
                        }
                    }
                ).then(response => {
                    setTimeout(() => this.isSearching = false, 1000);
                    const currentCourses = this.courses.map(c => c.id);
                    this.availableCourses = response.data.filter(course => !currentCourses.includes(course.id));
                    this.noCoursesFound = this.availableCourses.length === 0;
                }).catch(error => {
                    this.isSearching = false;
                    STUDIP.Report.error(this.$gettext('Es ist ein Fehler aufgetreten.'), error);
                });
            }
        },
        addRule() {
            this.ruleType = '';
            this.showRuleSelector = true;
        },
        closeRuleSelector() {
            this.ruleType = '';
            this.ruleId = '';
            this.singleRule = null;
            this.showRuleSelector = false;
        },
        closeRuleConfig() {
            this.ruleType = '';
            this.ruleId = '';
            this.singleRule = null;
            this.showRuleConfig = false;
        },
        configureRule(type, rule = null, index = null) {
            this.ruleType = type;
            this.ruleId = rule?.id;
            this.singleRule = rule;
            this.ruleIndex = index;
            this.showRuleSelector = false;
            this.showRuleConfig = true;
        },
        addRuleConfiguration(data) {
            if (!this.ruleId || this.ruleId === data.type + '_') {
                STUDIP.jsonapi.withPromises().post(
                    'admission-rules/' + data.type,
                    {
                        data: {
                            data: {
                                attributes: {
                                    payload: data.payload
                                }
                            }
                        }
                    }
                ).then(response => {
                    this.ruleType = '';
                    this.ruleId = '';
                    this.singleRule = null;
                    this.showRuleConfig = false;
                    if (this.ruleIndex !== null) {
                        this.rules[this.ruleIndex] = response.data;
                        this.ruleIndex = null;
                    } else {
                        this.rules.push(response.data);
                    }
                    this.checkForUserLists();
                });
            } else {
                STUDIP.jsonapi.withPromises().patch(
                    'admission-rules/' + this.ruleId,
                    {
                        data: {
                            data: {
                                attributes: {
                                    payload: data.payload
                                }
                            }
                        }
                    }
                ).then(response => {
                    this.ruleType = '';
                    this.ruleId = '';
                    this.singleRule = null;
                    this.showRuleConfig = false;
                    if (this.ruleIndex !== null) {
                        this.rules[this.ruleIndex] = response.data;
                        this.ruleIndex = null;
                    } else {
                        this.rules.push(response.data.data);
                    }
                    this.checkForUserLists();
                });
            }
        },
        removeRule(index) {
            this.rules.splice(index, 1);
            this.checkForUserLists();
        },
        removeCourse(index) {
            this.courses.splice(index, 1);
        },
        setInstitute(evt) {
            if (evt.currentTarget.value !== '') {
                this.addInstitute(
                    evt.currentTarget.value,
                    evt.currentTarget.options[evt.currentTarget.options.selectedIndex].textContent
                );
            }
        },
        addInstitute(returnValue, inputValue) {
            if (inputValue && !this.institutes.some(i => i.id === returnValue)) {
                this.institutes.push({ id: returnValue, name: inputValue });
            }
        },
        removeInstitute(index) {
            this.institutes.splice(index, 1);
        },
        storeCourseset() {
            const data = {
                data: {
                    attributes: this.coursesetData
                }
            };
            if (this.courseSetId === '') {

                STUDIP.jsonapi.withPromises().post(
                    'course-sets',
                    { data: data }
                ).then(() => {
                    this.relocateToIndex();
                });

            } else {

                STUDIP.jsonapi.withPromises().patch(
                    'course-sets/' + this.courseSetId,
                    { data: data }
                ).then(() => {
                    this.relocateToIndex();
                });

            }
        },
        cancel() {
            this.relocateToIndex(true);
        },
        configureCourses() {
            STUDIP.Dialog.fromURL(
                STUDIP.URLHelper.getURL('dispatch.php/admission/courseset/configure_courses/' + this.courseSetId)
            );
        },
        getApplicants() {
            STUDIP.Dialog.fromURL(
                STUDIP.URLHelper.getURL('dispatch.php/admission/courseset/applications_list/' + this.courseSetId)
            );
        },
        messageApplicants() {
            STUDIP.Dialog.fromURL(
                STUDIP.URLHelper.getURL('dispatch.php/admission/courseset/applicants_message/' + this.courseSetId)
            );
        },
        checkForUserLists() {
            const rule = this.rules?.filter(rule => rule.attributes.type === 'ParticipantRestrictedAdmission');
            this.hasUserLists = this.myUserLists.length > 0
                && (rule?.length > 0 ? rule[0].attributes.payload['distribution-time'] > 0 : false);
        },
        userListText(factor, count) {
            return factor < 1
                ? this.$gettext('%{number} Personen werden nachrangig eingetragen', { number: count })
                : this.$gettext('%{number} Personen werden bevorzugt', { number: count });
        },
        openUserListUsers() {
            STUDIP.Dialog.fromURL(
                STUDIP.URLHelper.getURL('dispatch.php/admission/courseset/factored_users/' + this.courseSetId)
            );
        },
        checkUncheckAll() {
            this.checkedCourses = this.allCoursesChecked ? [] : this.availableCourses.map(c => c.id);
        },
        relocateToIndex(cancel = false) {
            this.securityHandler.deactivate();
            if (!this.instantCourseSetView) {
                window.location = STUDIP.URLHelper.getURL('dispatch.php/admission/courseset');
            } else if (!cancel) {
                window.location.reload();
            } else {
                this.securityHandler.activate();
            }
        }
    },
    async created() {
        this.securityHandler.setPredicate(() => this.changed);

        // Load courseset if an ID is given
        if (this.courseSetId !== '') {
            const courseset = await STUDIP.jsonapi.withPromises().get(
                'course-sets/' + this.courseSetId,
                {data: {include: 'admission-rules,courses,institutes'}}
            );

            this.name = courseset.data.attributes.name;
            this.private = courseset.data.attributes.private;
            this.additional = courseset.data.attributes.infotext;
            this.numApplicants = courseset.data.attributes['num-applicants'];
            this.userLists = courseset.data.attributes['userlists'];

            courseset.included.forEach(entry => {
                switch (entry.type) {
                    case 'institutes':
                        this.addInstitute(entry.id, entry.attributes.name);
                        break;
                    case 'courses':
                        this.courses.push(entry);
                        break;
                    case 'admission-rules':
                        this.rules.push(entry);
                        break;
                }
            });

            this.checkForUserLists();
        } else if (this.myInstitutes.length === 1) {
            this.addInstitute(this.myInstitutes[0].id, this.myInstitutes[0].name);
        }

        if (!this.selectedSemester) {
            for (const value of Object.values(this.allSemesters)) {
                if (value.current) {
                    this.selectedSemester = value.id;
                }
            }
        }

        if (this.courseSearchterm) {
            this.getAvailableCourses();
        }

        this.initialCoursesetDataAsJSON = JSON.stringify(this.coursesetData);
    },
    watch: {
        checkedCourses: {
            handler(current) {
                if (this.$refs.proxy) {
                    this.$refs.proxy.indeterminate = current?.length > 0
                        && current?.length < this.availableCourses.length;
                }
            },
            deep: true
        }
    }
}
</script>

<style lang="scss">
table.assignments {
    margin-bottom: unset;
    width: 50%;

    .actions {
        text-align: right;
    }
}

button {
    &.add-rule-button {
        margin-bottom: 0;
    }

    img {
        vertical-align: text-bottom;
    }

}
</style>
