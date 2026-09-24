<template>
    <form action="#"
          method="post"
          enctype="multipart/form-data"
          class="questionnaire_edit default"
          @submit.prevent="submit()"
          :data-dialog="asDialog ? true : null"
          :data-secure="activateFormSecure"
    >
        <div class="editor">
            <div class="rightside" aria-live="polite" tabindex="0" ref="rightside">
                <div class="admin" v-if="activeTab === 'admin'">

                    <article aria-live="assertive" class="validation_notes studip">
                        <header>
                            <h1>
                                <studip-icon shape="info-circle" role="info" class="text-bottom validation_notes_icon"></studip-icon>
                                {{ $gettext('Hinweise zum Ausfüllen des Formulars') }}
                            </h1>
                        </header>
                        <div class="required_note">
                            <div aria-hidden="true">
                                {{ $gettext('Pflichtfelder sind mit Sternchen gekennzeichnet.') }}
                            </div>
                            <div class="sr-only">
                                {{ $gettext('Dieses Formular enthält Pflichtfelder.') }}
                            </div>
                        </div>
                        <div v-if="validationNotice && !data.title">
                            {{ $gettext('Folgende Angaben müssen korrigiert werden, um das Formular abschicken zu können:') }}
                            <ul>
                                <li aria-describedby="questionnaire_title">{{ $gettext('Titel des Fragebogens') }}</li>
                            </ul>
                        </div>
                    </article>

                    <div class="formpart">
                        <label class="studiprequired" for="questionnaire_title">
                            <span class="textlabel">{{ $gettext('Titel des Fragebogens') }}</span>
                            <span title="Dies ist ein Pflichtfeld" aria-hidden="true" class="asterisk">*</span>
                        </label>
                        <input type="text" id="questionnaire_title" v-model="data.title" v-autofocus>
                    </div>

                    <div class="hgroup">
                        <label>
                            {{ $gettext('Startzeitpunkt') }}
                            <datetimepicker v-model="data.startdate"></datetimepicker>
                        </label>
                        <label>
                            {{ $gettext('Endzeitpunkt') }}
                            <datetimepicker v-model="data.stopdate"></datetimepicker>
                        </label>
                    </div>
                    <label>
                        <input type="checkbox" v-model="data.copyable" true-value="1" false-value="0">
                        {{ $gettext('Fragebogen zum Kopieren freigeben') }}
                    </label>
                    <label>
                        <input type="checkbox" v-model="data.anonymous" true-value="1" false-value="0">
                        {{ $gettext('Teilnehmende anonymisieren') }}
                    </label>
                    <label>
                        <input type="checkbox" v-model="data.editanswers" true-value="1" false-value="0">
                        {{ $gettext('Teilnehmende dürfen ihre Antworten revidieren') }}
                    </label>
                    <label>
                        {{ $gettext('Ergebnisse einsehbar') }}
                        <select v-model="data.resultvisibility">
                            <option value="always">{{ $gettext('Immer') }}</option>
                            <option value="afterending">{{ $gettext('Nach Ende der Befragung') }}</option>
                            <option value="afterparticipation">{{ $gettext('Nach der Teilnahme') }}</option>
                            <option value="never">{{ $gettext('Niemals') }}</option>
                        </select>
                    </label>
                </div>
                <div class="add_question file_select_possibilities" v-else-if="activeTab === 'add_question'">
                    <div>
                        <button v-for="(questiontype, key) in questionTypes" :key="key"
                                href=""
                                @click.prevent="addQuestion(questiontype.type)"
                        >
                            <studip-icon :shape="questiontype.icon" :size="40"></studip-icon>
                            {{questiontype.name}}
                        </button>
                    </div>
                </div>
                <div v-else>
                    <component :is="componentForQuestionIndex(indexForQuestion)"
                               :model-value="data.questions[indexForQuestion].questiondata"
                               @update:modelValue="(v) => data.questions[indexForQuestion].questiondata = v"
                               :question_id="data.questions[indexForQuestion].id"
                               :key="data.questions[indexForQuestion].id">
                    </component>
                </div>
            </div>
            <aside>
                <a class="admin"
                   :class="{active: activeTab === 'admin'}"
                   href="#"
                   @click.prevent="switchTab('admin')">
                    <span class="icon"><studip-icon shape="evaluation" :size="30" alt=""></studip-icon></span>
                    {{ $gettext('Einstellungen') }}
                </a>
                <draggable :list="data.questions"
                           handle=".drag-handle"
                           group="questions"
                           class="questions_container questions"
                           item-key="id"
                           tag="ul"
                >
                    <template #item="{ element }">
                        <li @mouseenter="hoverTab = element.id"
                             @mouseleave="hoverTab = null"
                             :class="{
                                 active: activeTab === element.id || activeTab === 'meta_' + element.id,
                                 hovered: hoverTab === element.id,
                             }"
                        >
                            <a href="#"
                               @click.prevent="switchTab(element.id)">
                                <span class="drag-handle"></span>
                                <span class="icon type">
                                <studip-icon :shape="questionTypes[element.questiontype].icon" :size="30" alt=""></studip-icon>
                            </span>

                                <div v-if="editInternalName !== element.id">{{ element.internal_name || questionTypes[element.questiontype].name}}</div>
                                <div v-else class="inline_editing">
                                    <input type="text" ref="editInternalName" v-model="tempInternalName" class="inlineediting_internal_name">
                                    <button @click="saveInternalName(element.id)">
                                        <studip-icon shape="accept" :size="20" :title="$gettext('Internen Namen speichern')"></studip-icon>
                                    </button>
                                    <button @click="editInternalName = null">
                                        <studip-icon shape="decline" :size="20" :title="$gettext('Internen Namen nicht speichern')"></studip-icon>
                                    </button>
                                </div>
                            </a>

                            <studip-action-menu :items="actionMenuItems"
                                                @copy="duplicateQuestion(element.id)"
                                                @rename="renameInternalName(element.id)"
                                                @moveup="moveQuestionUp(element.id)"
                                                @movedown="moveQuestionDown(element.id)"
                                                @delete="deleteQuestion(element.id)"></studip-action-menu>
                        </li>
                    </template>
                </draggable>
                <a :class="activeTab === 'add_question' ? 'add_question active' : 'add_question'"
                   href="#"
                   @click.prevent="switchTab('add_question')">
                    <span class="icon"><studip-icon shape="add" :size="30" alt=""></studip-icon></span>
                    {{ $gettext('Element hinzufügen') }}
                </a>
            </aside>
        </div>


        <footer data-dialog-button>
            <button class="button" name="questionnaire_store">
                {{ $gettext('Speichern') }}
            </button>
            <a href="#" class="button cancel">
                {{ $gettext('Abbrechen') }}
            </a>
        </footer>
    </form>
</template>
<script>
import draggable from 'vuedraggable';
import md5 from 'md5';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import StudipActionMenu from '@/vue/components/StudipActionMenu.vue';
import Datetimepicker from '@/vue/components/Datetimepicker.vue';
import {defineAsyncComponent} from 'vue';

const loadedComponents = {};

export default {
    name: 'questionnaireeditor',
    components: {
        Datetimepicker,
        StudipActionMenu,
        StudipIcon,
        draggable,
    },
    props: {
        asDialog: {
            type: Boolean,
            default: false,
        },
        questionData: Object,
        questionTypes: Object,
        rangeId: String,
        rangeType: String,
    },
    data() {
        return {
            activeTab: 'admin',
            data: {...this.questionData},
            editInternalName: null,
            form_secured: true,
            hoverTab: null,
            oldData: JSON.parse(JSON.stringify(this.questionData)),
            tempInternalName: '',
            validationNotice: false,
        };
    },
    methods: {
        componentForQuestionIndex(index) {
            const componentInfo = this.questionTypes[this.data.questions[index].questiontype].component;
            if (loadedComponents[componentInfo[0]] === undefined) {
                loadedComponents[componentInfo[0]] = componentInfo[1] === ''
                    ? defineAsyncComponent(() => import(`../../components/questionnaires/${componentInfo[0]}.vue`))
                    : defineAsyncComponent(() => import(/* webpackIgnore: true */componentInfo[1]));
            }

            return loadedComponents[componentInfo[0]];
        },
        addQuestion(questiontype) {
            let id = md5(`${STUDIP.USER_ID}_QUESTIONTYPE_${Math.random()}`);

            this.data.questions.push({
                id: id,
                questiontype: questiontype,
                internal_name: '',
                questiondata: {},
            });

            this.activeTab = id;
        },
        submit() {
            if (!this.data.title) {
                this.switchTab('admin');
                this.validationNotice = true;
                return;
            }
            const data = {
                title: this.data.title,
                copyable: this.data.copyable,
                anonymous: this.data.anonymous,
                editanswers: this.data.editanswers,
                startdate: this.data.startdate,
                stopdate: this.data.stopdate,
                resultvisibility: this.data.resultvisibility
            };
            const questions = this.data.questions.map(question => ({
                id: question.id,
                questiontype: question.questiontype,
                internal_name: question.internal_name,
                questiondata: question.questiondata,
            }));
            $.post(STUDIP.URLHelper.getURL('dispatch.php/questionnaire/store/' + (this.data.id || '')), {
                questionnaire: data,
                questions_data: JSON.stringify(questions),
                range_type: this.rangeType,
                range_id: this.rangeId
            }).done(() => {
                this.form_secured = false;
                this.$nextTick(() => {
                    location.reload();
                });
            }).fail(() => {
                STUDIP.Report.error('Could not save questionnaire.', '');
            });
        },
        getIndexForQuestion(question_id) {
            for (let i in this.data.questions) {
                if (
                    this.data.questions[i].id === question_id
                    || this.data.questions[i].id === question_id.substring(5)
                ) {
                    return parseInt(i, 10);
                }
            }

            return null;
        },
        duplicateQuestion(question_id) {
            const i = this.getIndexForQuestion(question_id);
            const id = md5(`${STUDIP.USER_ID}_QUESTIONTYPE_${Math.random()}`);
            this.data.questions.push({
                id: id,
                questiontype: this.data.questions[i].questiontype,
                internal_name: this.data.questions[i].internal_name,
                questiondata: JSON.parse(JSON.stringify(this.data.questions[i].questiondata)),
            });
            this.activeTab = id;
        },
        deleteQuestion(question_id) {
            STUDIP.Dialog.confirm(this.$gettext('Wirklich löschen?')).done(() => {
                const index = this.getIndexForQuestion(question_id);
                this.data.questions.splice(index, 1);
                this.switchTab('add_question');
            })
        },
        switchTab(tab_id) {
            this.activeTab = tab_id;
        },
        objectsEqual(obj1, obj2) {
            return _.isEqual(obj1, obj2);
        },
        renameInternalName(question_id) {
            this.editInternalName = question_id;
            let index = this.getIndexForQuestion(question_id);
            this.tempInternalName = this.data.questions[index].internal_name;
            this.$nextTick(() => {
                this.$refs.editInternalName[0].focus();
            });
        },
        saveInternalName(question_id) {
            let index = this.getIndexForQuestion(question_id);
            this.data.questions[index].internal_name = this.tempInternalName;
            this.editInternalName = null;
        },
        moveQuestionDown(question_id) {
            let index = this.getIndexForQuestion(question_id);
            if (index < this.data.questions.length - 1) {
                let question = this.data.questions[index];
                this.data.questions[index] = this.data.questions[index + 1];
                this.data.questions[index + 1] = question;
                this.$forceUpdate();
            }
        },
        moveQuestionUp(question_id) {
            let index = this.getIndexForQuestion(question_id);
            if (index > 0) {
                let question = this.data.questions[index];
                this.data.questions[index] = this.data.questions[index - 1];
                this.data.questions[index - 1] = question;
                this.$forceUpdate();
            }
        }
    },
    computed: {
        actionMenuItems() {
            return [
                {label: this.$gettext('Umbenennen'), icon: 'edit', emit: 'rename'},
                {label: this.$gettext('Frage kopieren'), icon: 'copy', emit: 'copy'},
                {label: this.$gettext('Frage nach oben verschieben'), icon: 'arr_1up', emit: 'moveup'},
                {label: this.$gettext('Frage nach unten verschieben'), icon: 'arr_1down', emit: 'movedown'},
                {label: this.$gettext('Frage löschen'), icon: 'trash', emit: 'delete'},
            ];
        },
        activateFormSecure() {
            return this.form_secured && !this.objectsEqual(this.oldData, this.data) ? true : null;
        },
        indexForQuestion() {
            return this.getIndexForQuestion(this.activeTab);
        },
    },
}
</script>
