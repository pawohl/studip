<template>
    <div>
        <studip-dialog
            :title="$gettext('Feedback')"
            :closeText="$gettext('Schließen')"
            closeClass="cancel"
            :height="height"
            :width="width"
            @close="$emit('close')"
        >
            <template v-slot:dialogContent>
                <div v-if="!loadingFeedbackElement" class="feedback-dialog">
                    <feedback-five-stars-histogram :entries="entries" :vertical="true" />

                    <div class="feedback-dialog-content">
                        <template v-if="!editElement">
                            <div class="feedback-dialog-content-header">
                                <h2>
                                    {{ feedbackElement?.attributes?.question }}
                                </h2>
                                <button class="as-link" @click="editElement = true">
                                    <studip-icon shape="edit" />
                                </button>
                                <button class="as-link" @click="showDeleteFeedbackDialog = true">
                                    <studip-icon shape="trash" />
                                </button>
                            </div>
                            <div v-if="hasDescription">
                                <h3>{{ $gettext('Beschreibung') }}</h3>
                                <p>{{ description }}</p>
                            </div>
                        </template>
                        <feedback-element-update
                            v-else
                            :feedbackElementId="feedbackElementId"
                            @cancel="editElement = false"
                            @submit="updateFeedbackElement"
                        />

                        <template v-if="!currentUserIsAuthor">
                            <h3>{{ $gettext('Meine Bewertung') }}</h3>
                            <feedback-entry-create
                                v-if="!hasCurrentUserEntry || editEntry"
                                :feedbackElement="feedbackElement"
                                :entry="currentUserEntry[0]"
                                :currentUser="currentUser"
                                @submit="editEntry = false"
                                @cancel="editEntry = false"
                            />
                            <feedback-entry-box
                                v-else
                                class="current-user-entry"
                                :entry="currentUserEntry[0]"
                                :canEdit="true"
                                :canDelete="true"
                                @edit="editEntry = true"
                                @delete="showDeleteEntry"
                            />
                        </template>

                        <h3>{{ $gettext('Bewertungen') }}</h3>
                        <ul>
                            <li v-for="entry in otherUserEntries" :key="entry.id">
                                <feedback-entry-box
                                    :entry="entry"
                                    :canDelete="canEditFeedbackElement"
                                    @delete="showDeleteEntry"
                                />
                            </li>
                        </ul>
                        <p v-if="entries.length === 0">
                            {{ $gettext('Es wurden noch keine Bewertungen abgegeben.') }}
                        </p>
                        <p v-if="otherUserEntries.length === 0 && entries.length > 0">
                            {{ $gettext('Es wurden noch keine weiteren Bewertungen abgegeben.') }}
                        </p>
                    </div>
                    <studip-dialog
                        v-if="showDeleteEntryDialog"
                        :title="$gettext('Feedback-Eintrag löschen')"
                        :question="$gettext('Möchten Sie den Eintrag wirklich unwiderruflich löschen?')"
                        height="200"
                        @confirm="executeDeleteFeedbackEntry"
                        @close="closeDeleteEntry"
                    />
                </div>
                <studip-progress-indicator v-else :description="$gettext('Lade Bewertungen…')" />
            </template>
        </studip-dialog>
        <studip-dialog
            v-if="showDeleteFeedbackDialog"
            :title="$gettext('Feedback-Element löschen')"
            :question="
                $gettext(
                    'Möchten Sie das Feedback-Element wirklich unwiderruflich löschen? Alle Bewertungen werden ebenfalls gelöscht!'
                )
            "
            height="200"
            @confirm="executeDeleteFeedback"
            @close="showDeleteFeedbackDialog = false"
        ></studip-dialog>
    </div>
</template>
<script>
import FeedbackElementUpdate from './FeedbackElementUpdate.vue';
import FeedbackEntryBox from './FeedbackEntryBox.vue';
import FeedbackEntryCreate from './FeedbackEntryCreate.vue';
import FeedbackFiveStarsHistogram from './FeedbackFiveStarsHistogram.vue';
import StudipProgressIndicator from './../StudipProgressIndicator.vue';

import { mapActions, mapGetters } from 'vuex';

export default {
    name: 'feedback-dialog',
    components: {
        FeedbackElementUpdate,
        FeedbackEntryBox,
        FeedbackEntryCreate,
        FeedbackFiveStarsHistogram,
        StudipProgressIndicator,
    },
    emits: ['close', 'deleted'],
    props: {
        feedbackElementId: {
            type: Number,
            required: true,
        },
        currentUser: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            height: '0',
            width: '0',
            loadingFeedbackElement: false,
            editEntry: false,
            currentDeleteEntryId: null,
            showDeleteEntryDialog: false,
            showDeleteFeedbackDialog: false,
            editElement: false,
        };
    },
    computed: {
        ...mapGetters({
            feedbackElementById: 'feedback-elements/byId',
            canEditFeedbackElement: 'canEditFeedbackElement',
            feedbackEntries: 'feedback-entries/all',
        }),
        entries() {
            return this.feedbackEntries.filter(
                (entry) => parseInt(entry.relationships?.['feedback-element']?.data?.id) === this.feedbackElementId
            );
        },
        feedbackElement() {
            return this.feedbackElementById({ id: this.feedbackElementId }) ?? null;
        },
        currentUserIsAuthor() {
            return this.currentUser.id === this.feedbackElement?.relationships?.author?.data?.id;
        },
        currentUserEntry() {
            return this.entries.filter((entry) => this.isUserEntry(entry));
        },
        otherUserEntries() {
            return this.entries.filter((entry) => !this.isUserEntry(entry));
        },
        hasCurrentUserEntry() {
            return this.currentUserEntry.length > 0;
        },
        description() {
            return this.feedbackElement?.attributes?.description;
        },
        hasDescription() {
            return this.description !== '';
        },
    },
    methods: {
        ...mapActions({
            loadFeedbackElement: 'feedback-elements/loadById',
            deleteFeedbackEntries: 'feedback-entries/delete',
            deleteFeedbackElement: 'feedback-elements/delete',
        }),
        setDimensions() {
            this.height = (window.innerHeight * 0.8).toFixed(0);
            this.width = Math.min((window.innerWidth * 0.8).toFixed(0), 890).toFixed(0);
        },
        isUserEntry(entry) {
            return this.currentUser.id === entry.relationships?.author?.data?.id;
        },
        showDeleteEntry(entry) {
            this.currentDeleteEntryId = entry.id;
            this.showDeleteEntryDialog = true;
        },
        closeDeleteEntry() {
            this.showDeleteEntryDialog = false;
            this.currentDeleteEntryId = null;
        },
        executeDeleteFeedbackEntry() {
            this.deleteFeedbackEntries({ id: this.currentDeleteEntryId });
            this.closeDeleteEntry();
        },
        executeDeleteFeedback() {
            this.deleteFeedbackElement({ id: this.feedbackElementId }).then(() => {
                this.$emit('deleted');
                this.$emit('close');
            });
        },
        updateFeedbackElement() {
            this.editElement = false;
            this.loadElement();
        },
        async loadElement() {
            this.loadingFeedbackElement = true;
            await this.loadFeedbackElement({ id: this.feedbackElementId, options: { include: 'entries' } });
            this.loadingFeedbackElement = false;
        },
    },
    mounted() {
        this.setDimensions();
        this.loadElement();
    },
};
</script>
