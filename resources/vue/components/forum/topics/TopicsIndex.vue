<script setup>
import {debounce} from 'lodash';
import draggable from 'vuedraggable';
import {nextTick, ref, toRef} from 'vue';
import CreateTopic from './CreateTopic.vue';
import TopicItem from './TopicItem.vue';
import Loader from '../Loader.vue';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import EmptyForum from '../EmptyForum.vue';
import CategoryItem from '../categories/CategoryItem.vue';
import {useSortable} from '@/vue/composables/useSortable';
import StudipDialog from '@/vue/components/StudipDialog.vue';
import ShowTopic from "./ShowTopic.vue";
import ShowCategory from "../categories/ShowCategory.vue";

const forumConfig = useForumConfig();

const props = defineProps({
    topics: {
        type: Array,
        required: true
    },
    showEmptyForumLayout: {
        type: Boolean,
        default: false
    },
    isLoading: {
        type: Boolean,
        default: false
    },
    categoryId: {
        type: String
    }
});

const currentTopic = ref(null);
const currentCategory = ref(null);
const topicsRef = toRef(props, 'topics');

const {
    sortedData: sortedTopics,
    sortBy,
    getSortClass,
    getAriaSortString,
    getAriaSortLabel
} = useSortable(topicsRef);

const updateTopicsOrder = async () => {
    try {
        const topicIds = sortedTopics.value.map(({ id }) => id);

        const data = {
            attributes: {
                'topic-ids': topicIds
            },
            relationships: {
                range: {
                    data: {
                        type: 'courses',
                        id: STUDIP.URLHelper.parameters.cid
                    }
                }
            }
        };

        await STUDIP.jsonapi.withPromises().PATCH(
            'forum-topics/sort',
            { data: { data } }
        );
    } catch (error) {
        STUDIP.Report.error(error);
    }
}


const updateOrderDebounced = debounce(updateTopicsOrder, 2000);
const assistiveLive = ref('');

const swapItem = (itemId, step) => {
    const index = sortedTopics.value.findIndex(({ id }) => id === itemId);
    const newIndex = index + step;

    if (newIndex < 0 || newIndex >= sortedTopics.value.length) {
        return;
    }

    const temp = sortedTopics.value[newIndex];
    sortedTopics.value[newIndex] = sortedTopics.value[index];
    sortedTopics.value[index] = temp;

    nextTick(() => {
        document.getElementById(`sort-handle-${itemId}`)?.focus();
        assistiveLive.value = $gettext(
            'Aktuelle Position in der Liste: %{index} von %{length}.',
            { index: newIndex + 1, length: sortedTopics.value.length }
        );

        updateOrderDebounced();
    });
}

const showTopicDialog = topic => currentTopic.value = topic;
const showCategoryDialog = category => currentCategory.value = category;
</script>

<template>
    <Loader v-if="isLoading" />
    <template v-else>
        <template v-if="sortedTopics.length || !showEmptyForumLayout">
            <span aria-live="assertive" class="sr-only">{{ assistiveLive }}</span>
            <div v-if="forumConfig.tileLayout">
                <draggable
                    v-if="sortedTopics.length"
                    v-model="sortedTopics"
                    item-key="topic_id"
                    :animation="200"
                    @end="updateTopicsOrder"
                    :disabled="!forumConfig.isModerator"
                    class="topic-cards-container"
                    :class="{
                        '--fill-free-space': sortedTopics.length > 1
                    }"
                    role="listbox"
                    handle=".drag-handle"
                    tag="ul">
                    <template #item="{element}">
                        <li>
                            <CategoryItem
                                v-if="element.category && !forumConfig.isCategoriesHidden"
                                :category="element.category"
                                @swapCategory="swapItem"
                                @showCategory="showCategoryDialog(element)"
                            />
                            <TopicItem
                                v-else
                                :topic="element"
                                @swapTopic="swapItem"
                                @showTopic="showTopicDialog(element)"
                            />
                        </li>
                    </template>
                    <template v-if="forumConfig.isModerator" #footer>
                        <li key="footer">
                            <div class="topic-card topic-card--new-topic">
                                <CreateTopic
                                    class="--with-label"
                                    :category_id="categoryId"
                                    :label="$gettext('Neues Thema anlegen')"
                                />
                            </div>
                        </li>
                    </template>
                </draggable>
                <div v-else-if="forumConfig.isModerator" class="topic-cards-container">
                    <div class="topic-card topic-card--new-topic">
                        <CreateTopic
                            :category_id="categoryId"
                            class="--with-label"
                            :label="$gettext('Neues Thema anlegen')"
                        />
                    </div>
                </div>
            </div>
            <table v-else class="default forum-table forum-table--topics-index">
                <colgroup>
                    <col>
                    <col style="width: 15%;">
                    <col style="width: 15%;">
                    <col style="width: 15%;">
                    <col style="width: 15%;">
                    <col style="width: 5%">
                </colgroup>
                <thead>
                    <tr class="sortable">
                        <th
                            scope="col"
                            :class="getSortClass('name')"
                            :aria-sort="getAriaSortString('name')"
                            :aria-label="getAriaSortLabel('name', $gettext('Name'))"
                        >
                            <button
                                type="button"
                                class="as-link"
                                @click="sortBy('name')"
                                :title="$gettext('Nach Name sortieren')"
                                :aria-label="$gettext('Nach Name sortieren')"
                            >
                                {{ $gettext('Name') }}
                            </button>
                        </th>
                        <th
                            scope="col"
                            :class="getSortClass('meta.discussions_count')"
                            :aria-sort="getAriaSortString('meta.discussions_count')"
                            :aria-label="getAriaSortLabel('meta.discussions_count', $gettext('Anzahl der Diskussionen'))"
                        >
                            <button
                                type="button"
                                class="as-link"
                                @click="sortBy('meta.discussions_count')"
                                :title="$gettext('Nach Anzahl der Diskussionen sortieren')"
                                :aria-label="$gettext('Nach Anzahl der Diskussionen sortieren')"
                            >
                                {{ $gettext('Diskussionen') }}
                            </button>
                        </th>
                        <th
                            scope="col"
                            :class="getSortClass('meta.users_count')"
                            :aria-sort="getAriaSortString('meta.users_count')"
                            :aria-label="getAriaSortLabel('meta.users_count', $gettext('Anzahl der Teilnehmenden'))"
                        >
                            <button
                                type="button"
                                class="as-link"
                                @click="sortBy('meta.users_count')"
                                :title="$gettext('Nach Anzahl der Teilnehmenden sortieren')"
                                :aria-label="$gettext('Nach Anzahl der Teilnehmenden sortieren')"
                            >
                                {{ $gettext('Teilnehmende') }}
                            </button>
                        </th>
                        <th
                            scope="col"
                            :class="getSortClass('meta.postings_count')"
                            :aria-sort="getAriaSortString('meta.postings_count')"
                            :aria-label="getAriaSortLabel('meta.postings_count', $gettext('Anzahl der Beiträge'))"
                        >
                            <button
                                type="button"
                                class="as-link"
                                @click="sortBy('meta.postings_count')"
                                :title="$gettext('Nach Anzahl der Beiträge sortieren')"
                                :aria-label="$gettext('Nach Anzahl der Beiträge sortieren')"
                            >
                                {{ $gettext('Beiträge') }}
                            </button>
                        </th>
                        <th
                            scope="col"
                            :class="getSortClass('meta.recent_activity')"
                            :aria-sort="getAriaSortString('meta.recent_activity')"
                            :aria-label="getAriaSortLabel('meta.recent_activity', $gettext('Letzte Aktivität'))"
                        >
                            <button
                                type="button"
                                class="as-link"
                                @click="sortBy('meta.recent_activity')"
                                :title="$gettext('Nach letzter Aktivität sortieren')"
                                :aria-label="$gettext('Nach letzter Aktivität sortieren')"
                            >
                                {{ $gettext('Letzte Aktivität') }}
                            </button>
                        </th>
                        <th scope="col">
                            <span class="sr-only">{{ $gettext('Aktionen') }}</span>
                        </th>
                    </tr>
                </thead>
                <draggable
                    v-if="sortedTopics.length"
                    v-model="sortedTopics"
                    item-key="topic_id"
                    :animation="200"
                    @end="updateTopicsOrder"
                    :disabled="!forumConfig.isModerator"
                    handle=".drag-handle"
                    tag="tbody">
                    <template #item="{element}">
                        <CategoryItem
                            v-if="element.category && !forumConfig.isCategoriesHidden"
                            renderType="tr"
                            :category="element.category"
                            @swapCategory="swapItem"
                            @showCategory="showCategoryDialog(element)"
                        />
                        <TopicItem
                            v-else
                            renderType="tr"
                            :topic="element"
                            @swapTopic="swapItem"
                            @showTopic="showTopicDialog(element)"
                        />
                    </template>
                </draggable>
                <tbody v-else>
                    <tr>
                        <td colspan="6">
                            {{ $gettext('Es sind noch keine Themen vorhanden.') }}
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="forumConfig.isModerator">
                    <tr class="new-topic">
                        <td colspan="6">
                            <div class="footer-actions-container">
                                <CreateTopic
                                    :category_id="categoryId"
                                    class="--with-label"
                                    :label="$gettext('Neues Thema anlegen')"
                                />
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <slot name="pagination" />

            <StudipDialog
                v-if="currentTopic?.id"
                :title="$gettext('Detaillierte Information')"
                :closeText="$gettext('Schließen')"
                height="700"
                width="600"
                @close="currentTopic = null"
            >
                <template #dialogContent>
                    <div class="forum">
                        <ShowTopic :topic="currentTopic" />
                    </div>
                </template>
            </StudipDialog>

            <StudipDialog
                v-if="currentCategory?.id"
                :title="$gettext('Detaillierte Information')"
                :closeText="$gettext('Schließen')"
                height="700"
                width="600"
                @close="currentCategory = null"
            >
                <template #dialogContent>
                    <div class="forum">
                        <ShowCategory :category="currentCategory" />
                    </div>
                </template>
            </StudipDialog>
        </template>
        <EmptyForum v-else-if="showEmptyForumLayout" />
    </template>
</template>
