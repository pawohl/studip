<script setup>
import {computed, reactive, ref, useTemplateRef} from 'vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import {numberFormatter} from '@/assets/javascripts/lib/number_formatter';
import useDetectOutsideClick from '@/vue/composables/useDetectOutsideClick';
import {useForumPost} from '@/vue/store/pinia/forum/ForumPost';
import {deserializeJSONAPIResponse} from '@/assets/javascripts/lib/jsonapiUtils';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import PostReactionShow from '@/vue/components/forum/posts/PostReactionShow.vue';
import StudipDialog from '@/vue/components/StudipDialog.vue';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';

const forumConfig = useForumConfig();
const forumDiscussionPost = useForumPost();

const props = defineProps({
    posting_id: {
        type: String,
        required: true,
    },
    postReactions: {
        type: Array,
        required: true
    }
});

const showReactions = ref(false);
const reactionStatusMessage = ref(null);
const isLoading = ref(false);

const reactionEmojis = computed(() => forumConfig.reactionEmojis);
const transformedPostReactions = computed(() =>
    props.postReactions.map(reaction => ({
        ...reaction,
        user: reaction?.user ?? { formatted_name: $gettext('Unbekannt') }
    }))
);

const groupedReactions = computed(() => Object.groupBy(transformedPostReactions.value, ({ emoji }) => emoji));

const announceToScreenReader = message => reactionStatusMessage.value.textContent = message;

const getPostReactionJSONAPIObject = emoji => ({
    data: {
        type: 'forum-posting-reactions',
        attributes: {
            emoji: emoji
        },
        relationships: {
            posting: {
                data: {
                    type: 'forum-postings',
                    id: props.posting_id
                }
            }
        }
    }
});

const storeReaction = async emoji => {
    try {
        const response = await STUDIP.jsonapi.withPromises().POST(
            'forum-posting-reactions?include=user&fields[users]=id,username,formatted-name',
            { data: getPostReactionJSONAPIObject(emoji) }
        );

        const reaction = await deserializeJSONAPIResponse(response);
        forumDiscussionPost.addPostReaction(reaction, props.posting_id);
        showReactions.value = false;
        return reaction;
    } catch (error) {
        STUDIP.Report.error(error);
    }
}

const deleteReaction = async reactionId => {
    try {
        await STUDIP.jsonapi.withPromises().DELETE(`forum-posting-reactions/${reactionId}`);
        forumDiscussionPost.removePostReaction(reactionId, props.posting_id);
        return true;
    } catch (error) {
        STUDIP.Report.error(error);
    }
}

const toggleReaction = async (emoji, reactions = transformedPostReactions.value) => {
    if (forumConfig.allowGuestAccess || isLoading.value) {
        return;
    }

    isLoading.value = true;
    const userReaction = findUserReaction(emoji, reactions);

    if (userReaction) {
        await deleteReaction(userReaction.id);
        announceToScreenReader($gettext('Reaktion wurde entfernt.'));
    } else {
        await storeReaction(emoji);
        announceToScreenReader($gettext('Reaktion wurde hinzugefügt.'));
    }

    isLoading.value = false;
}

const findUserReaction = (emoji, reactions = transformedPostReactions.value) => reactions.find(reaction => reaction.user.id === STUDIP.USER_ID && reaction.emoji === emoji);
const getEmojiLabel = emoji => forumConfig.reactionEmojis.find((emojis) => emojis.value === emoji)?.label;

const reactionCreate = useTemplateRef('reactionCreate');
useDetectOutsideClick(reactionCreate, () => showReactions.value = false);

const reactionShowDialog = reactive({
    isOpen: false,
    emoji: 'all'
});
</script>

<template>
    <div class="post-reactions-container">
        <div aria-live="polite" class="sr-only" role="status" ref="reactionStatusMessage"></div>

        <template v-if="transformedPostReactions.length">
            <template v-for="(reaction, emoji) in groupedReactions" :key="emoji">
                <button
                    type="button"
                    class="post-reaction button-base"
                    :class="{
                        'post-reaction--active': findUserReaction(emoji, reaction)
                    }"
                    :title="findUserReaction(emoji, reaction)  ? $gettext('Reaktion zurücknehmen') : $gettext('Reaktion hinzufügen')"
                    :aria-label="findUserReaction(emoji, reaction) ? $gettext('Reaktion zurücknehmen') : $gettext('Reaktion hinzufügen')"
                    @click="toggleReaction(emoji, reaction)">
                    <span class="emoji-icon" aria-hidden="true">{{ emoji }}</span>
                    <span class="sr-only">{{ getEmojiLabel(emoji) }}</span>
                    <span>{{ numberFormatter(reaction.length, 1) }}</span>
                </button>
            </template>
        </template>
        <div ref="reactionCreate" class="post-reactions">
            <div class="post-reactions__button-group">
                <button
                    v-if="!forumConfig.allowGuestAccess"
                    type="button"
                    class="post-reactions__add-reaction button-base"
                    :title="$gettext('Reagieren')"
                    :aria-label="$gettext('Reagieren')"
                    :aria-pressed="showReactions"
                    @click="showReactions = !showReactions">
                    <StudipIcon shape="add-reaction" class="add-reaction-icon" :size="18" aria-hidden="true" />
                </button>
                <button
                    v-if="transformedPostReactions.length"
                    type="button"
                    class="post-reactions__show-reactions button-base"
                    :title="$gettext('Reaktionen anzeigen')"
                    :aria-label="$gettext('%{count} Reaktionen anzeigen', { count: transformedPostReactions.length })"
                    @click="reactionShowDialog.isOpen = true">
                    {{ numberFormatter(transformedPostReactions.length, 1) }}
                </button>
            </div>
            <Transition name="fade">
                <div v-if="showReactions" class="post-reactions__container">
                    <template v-for="(emoji, index) in reactionEmojis" :key="index">
                        <button
                            type="button"
                            :class="{
                                'post-reaction--active': findUserReaction(emoji.value)
                            }"
                            :title="$gettext('Auf diesen Beitrag mit „%{emojiLabel}“ reagieren', { emojiLabel: emoji.label })"
                            :aria-label="$gettext('Auf diesen Beitrag mit „%{emojiLabel}“ reagieren', { emojiLabel: emoji.label })"
                            @click="toggleReaction(emoji.value)"
                        >
                            <span class="emoji-icon" aria-hidden="true">{{ emoji.value }}</span>
                        </button>
                    </template>
                </div>
            </Transition>
        </div>
    </div>

    <StudipDialog
        v-if="reactionShowDialog.isOpen && transformedPostReactions.length"
        :title="$gettext('Reaktionen anzeigen')"
        :closeText="$gettext('Schließen')"
        closeClass="cancel"
        height="700"
        width="600"
        @close="reactionShowDialog.isOpen = false"
    >
        <template #dialogContent>
            <div class="forum">
                <div class="tab post-reactions-dialog">
                    <div class="tab__buttons" role="radiogroup" :aria-label="$gettext('Emoji-Filter')">
                        <div class="tab__button">
                            <input
                                type="radio"
                                id="reaction-all"
                                name="reaction-filter"
                                value="all"
                                v-model="reactionShowDialog.emoji"
                            />
                            <label class="button-base" for="reaction-all" :class="{ 'active': reactionShowDialog.emoji === 'all' }">
                                {{ $gettext('Alle') }}
                                <span>{{ numberFormatter(transformedPostReactions.length, 1) }}</span>
                            </label>
                        </div>
                        <div
                            v-for="(reaction, emoji) in groupedReactions"
                            :key="emoji"
                            class="tab__button"
                        >
                            <input
                                type="radio"
                                :id="`reaction-${emoji}`"
                                name="reaction-filter"
                                :value="emoji"
                                v-model="reactionShowDialog.emoji"
                            />
                            <label class="button-base" :for="`reaction-${emoji}`" :class="{ 'active': reactionShowDialog.emoji === emoji }">
                                <span class="emoji-icon" aria-hidden="true">{{ emoji }}</span>
                                <span class="sr-only">{{ getEmojiLabel(emoji) }}</span>
                                <span>{{ numberFormatter(reaction.length, 1) }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="tab__content">
                        <PostReactionShow :reactions="transformedPostReactions" :emoji="reactionShowDialog.emoji" />
                    </div>
                </div>
            </div>
        </template>
    </StudipDialog>
</template>
