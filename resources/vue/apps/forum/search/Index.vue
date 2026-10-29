<script setup>
import ForumApp from '@/vue/components/forum/ForumApp.vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import {computed, onMounted, reactive, ref} from 'vue';
import SelectTopicInput from '@/vue/components/forum/topics/SelectTopicInput.vue';
import SelectTagsInput from '@/vue/components/forum/SelectTagsInput.vue';
import SelectDiscussionType from '@/vue/components/forum/discussions/SelectDiscussionType.vue';
import {getTopicURL} from '@/vue/components/forum/helpers/urls';
import SelectUserInput from '@/vue/components/forum/SelectUserInput.vue';
import DiscussionIndex from '@/vue/components/forum/discussions/DiscussionIndex.vue';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import StudipSelect from '@/vue/components/StudipSelect.vue';
import {highlightText, removeHighlight} from '@/vue/components/forum/helpers';
import {deserializeJSONAPIResponse} from '@/assets/javascripts/lib/jsonapiUtils';
import StudipPagination from '@/vue/components/StudipPagination.vue';

const discussionStatuses = [
    {
        value: 1,
        label: $gettext('Alle')
    },
    {
        value: 2,
        label: $gettext('Geöffnet')
    },
    {
        value: 3,
        label: $gettext('Geschlossen')
    }
];

const CSRF = STUDIP.CSRF_TOKEN;

const props = defineProps({
    filter: {
        type: Object,
        required: true
    },
    topics: {
        type: Array,
        required: true
    },
    discussionTypes: {
        type: Array,
        required: true
    },
    tags: {
        type: Array,
        required: true
    },
    courseMembers: {
        type: Array,
        required: true
    }
});

const discussions = ref([]);
const pagination = ref({});
const isLoading = ref(false);
const isFilterVisible = ref(true);

const searchForm = reactive({
    ...(props.filter.keyword && { keyword: props.filter.keyword }),
    ...(props.filter.begin && { begin: parseToDateString(props.filter.begin) }),
    ...(props.filter.end && { end: parseToDateString(props.filter.end) }),
    ...(props.filter.status && { status: discussionStatuses.find(status => status.value === props.filter.status) }),
    ...(props.filter.topic_ids && { topics: props.topics.filter(({ topic_id }) => props.filter.topic_ids.includes(topic_id)) }),
    ...(props.filter.tag_ids && { tags: props.tags.filter(({ id }) => props.filter.tag_ids.includes(id.toString())) }),
    ...(props.filter.type_ids && { types: props.discussionTypes.filter(({ type_id }) => props.filter.type_ids.includes(type_id.toString())) }),
    ...(props.filter.user_ids && { authors: props.courseMembers.filter(({ user_id }) => props.filter.user_ids.includes(user_id)) }),
});

const availableTags = computed(() => {
    if (searchForm.tags && searchForm.tags.length > 0) {
        const selectedTagsId = searchForm.tags.map(({ id }) => id);
        return props.tags.filter(({ id }) => selectedTagsId.indexOf(id) < 0);
    }

    return props.tags;
});

const availableTopics = computed(() => {
    if (searchForm.topics && searchForm.topics.length > 0) {
        const selectedTopicsId = searchForm.topics.map(({ topic_id }) => topic_id);
        return props.topics.filter(({ topic_id }) => selectedTopicsId.indexOf(topic_id) < 0);
    }

    return props.topics;
});

const availableTypes = computed(() => {
    if (searchForm.types && searchForm.types.length > 0) {
        const selectedTypesId = searchForm.types.map(({ type_id }) => type_id);
        return props.discussionTypes.filter(({ type_id }) => selectedTypesId.indexOf(type_id) < 0);
    }

    return props.discussionTypes;
});

const resetSearchForm = () => {
    Object.assign(searchForm, {
        keyword: '',
        status: null,
        begin: null,
        end: null,
        topics: [],
        tags: [],
        types: [],
        authors: []
    });

    discussions.value = [];
}

function parseToDateString(timestamp) {
    if (!timestamp) {
        return '';
    }

    return STUDIP.Dates.unixTimestampToISO(timestamp).split('T')[0];
}

const filterQueryParams = computed(() => {
    const filter = {
        ...(searchForm.keyword && { 'keyword': searchForm.keyword }),
        ...(searchForm.status && { 'status': parseInt(searchForm.status.value) }),
        ...(searchForm.begin && { 'begin': STUDIP.Dates.stringToUnixTimestamp(searchForm.begin) }),
        ...(searchForm.end && { 'end': STUDIP.Dates.stringToUnixTimestamp(searchForm.end) }),
        ...(searchForm.types?.length && { 'type-ids': searchForm.types.map(({ type_id }) => type_id).join(',') }),
        ...(searchForm.topics?.length && { 'topic-ids': searchForm.topics.map(({ topic_id }) => topic_id).join(',') }),
        ...(searchForm.authors?.length && { 'user-ids': searchForm.authors.map(({ user_id }) => user_id).join(',') }),
        ...(searchForm.tags?.length && { 'tag-ids': searchForm.tags.map(({ id }) => id).join(',') }),
    };

    if (Object.keys(filter).length === 0) {
        return '';
    }

    return Object.entries(filter)
        .map(([key, value]) => `filter[${encodeURIComponent(key)}]=${encodeURIComponent(value)}`)
        .join('&');
});

const fetchDiscussions = async (_, offset = 0) => {
    try {
        isLoading.value = true;

        const response = await STUDIP.jsonapi.withPromises().GET(
            `courses/${STUDIP.URLHelper.parameters.cid}/forum-discussions`,
            {
                data: {
                    include: `category,discussion-type,members,tags,user&fields[users]=id&${filterQueryParams.value}`,
                    page: { offset }
                }
            }
        );

        pagination.value = {
            ...response.meta.page,
            currentPage: response.meta.page.offset / response.meta.page.limit,
            links: response.links
        };

        discussions.value = await deserializeJSONAPIResponse(response);
    } catch (error) {
        STUDIP.Report.error(error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(async () => {
    if (filterQueryParams.value) {
        await fetchDiscussions();
    }

    if (searchForm.keyword?.length > 1 && discussions.value.length) {
        highlightText(searchForm.keyword, '.discussion-title');

        // remove highlights
        document.getElementById("forum-search").addEventListener("click", function() {
            removeHighlight('.discussion-title mark');
        });
    }
});
</script>

<template>
    <ForumApp id="forum-search">
        <form action="#" @submit.prevent="fetchDiscussions" method="post" class="default search-container use-utility-classes">
            <input type="hidden" :name="CSRF.name" :value="CSRF.value">
            <div class="search-controls">
                <div class="search-input-container">
                    <label for="search-field">
                        {{ $gettext('Diskussionen und Beiträge durchsuchen') }}
                    </label>
                    <input id="search-field" name="q" type="text" v-model="searchForm.keyword" :placeholder="$gettext('Suchbegriff eingeben')"/>
                </div>
                <button
                    type="submit"
                    class="button button--icon-label"
                    :title="$gettext('Suchen')"
                    :aria-label="$gettext('Suchen')"
                >
                    <StudipIcon shape="search" :size="20" aria-hidden="true" />
                    {{ $gettext('Suchen') }}
                </button>
                <button
                    type="button"
                    class="button button--icon-only"
                    @click="resetSearchForm"
                    :title="$gettext('Zurücksetzen')"
                    :aria-label="$gettext('Zurücksetzen')"
                >
                    <StudipIcon shape="decline" :size="20" aria-hidden="true" />
                </button>
            </div>

            <div class="filter-summary-container">
                <template v-for="topic in searchForm.topics" :key="topic.topic_id">
                    <div class="badge">
                        <a
                            target="_blank"
                            class="flex gap-5 items-center"
                            :href="getTopicURL(topic.topic_id)"
                            :title="$gettext('Zum Thema')"
                            :aria-label="$gettext('Zum Thema: %{name}', { name: topic.name })"
                        >
                            <span :style="{ backgroundColor: topic.color ?? '#EDEDED', height: '14px', width: '14px'}" aria-hidden="true"></span>
                            {{ topic.name }}
                        </a>
                        <button
                            type="button"
                            class="action button-base"
                            @click="searchForm.topics = searchForm.topics.filter(t => t.topic_id !== topic.topic_id)"
                            :title="$gettext('Entfernen')"
                            :aria-label="$gettext('Ausgewähltes Thema entfernen')"
                        >
                            <StudipIcon shape="decline" :size="15" aria-hidden="true" />
                        </button>
                    </div>
                </template>
                <template v-for="type in searchForm.types" :key="type.type_id">
                    <div class="badge" :title="type.name">
                        <StudipIcon :shape="type.icon" :size="15" aria-hidden="true" />
                        <span>{{ type.name }}</span>
                        <button
                            class="action button-base"
                            @click="searchForm.types = searchForm.types.filter(t => t.type_id !== type.type_id)"
                            :title="$gettext('Entfernen')"
                            :aria-label="$gettext('Ausgewählten Diskussionstyp entfernen')"
                        >
                            <StudipIcon shape="decline" :size="15" aria-hidden="true" />
                        </button>
                    </div>
                </template>
                <template v-for="tag in searchForm.tags" :key="tag">
                    <div class="badge" :title="tag.name">
                        <span>{{ '#'+tag.name }}</span>
                        <button
                            type="button"
                            class="action button-base"
                            @click="searchForm.tags = searchForm.tags.filter(t => t.name !== tag.name)"
                            :title="$gettext('Entfernen')"
                            :aria-label="$gettext('Ausgewähltes Schlagwort entfernen')"
                        >
                            <StudipIcon shape="decline" :size="15" aria-hidden="true" />
                        </button>
                    </div>
                </template>

                <template v-for="user in searchForm.authors" :key="user.user_id">
                    <div class="badge">
                        <a
                            target="_blank"
                            class="flex gap-5 items-center"
                            :href="user.profile_url"
                            :title="$gettext('Zum Nutzer Profile')"
                            :aria-label="$gettext('Zum Nutzer Profile von %{name}', { name: user.name })"
                        >
                            <img width="15px" height="15px" :src="user.avatar_url" :alt="user.name" />
                            {{ user.name }}
                        </a>
                        <button
                            type="button"
                            class="action button-base"
                            @click="searchForm.authors = searchForm.authors.filter(u => u.name !== user.name)"
                            :title="$gettext('Entfernen')"
                            :aria-label="$gettext('Ausgewählte Autor/-in entfernen')"
                        >
                            <StudipIcon shape="decline" :size="15" />
                        </button>
                    </div>
                </template>
            </div>

            <hr />

            <div>
                <button
                    type="button"
                    class="toggle-filter-button button-base"
                    @click="isFilterVisible = !isFilterVisible"
                    :title="isFilterVisible ? $gettext('Erweiterte Filter zuklappen') : $gettext('Erweiterte Filter aufklappen')"
                    :aria-label="isFilterVisible ? $gettext('Erweiterte Filter zuklappen') : $gettext('Erweiterte Filter aufklappen')"
                    :aria-expanded="isFilterVisible.toString()"
                >
                    {{ $gettext('Erweiterte Filter') }}
                    <StudipIcon v-if="isFilterVisible" shape="arr_1up" :size="20" aria-hidden="true" />
                    <StudipIcon v-else shape="arr_1down" :size="20" aria-hidden="true" />
                </button>
                <div v-if="isFilterVisible" class="filter-controls">
                    <div>
                        <label for="select-topic-input">
                            {{ $gettext('Thema') }}
                        </label>
                        <SelectTopicInput
                            id="select-topic-input"
                            :options="availableTopics"
                            v-model="searchForm.topics"
                            :required="false"
                            multiple
                        />
                    </div>
                    <div>
                        <label for="select-discussion-type">
                            {{ $gettext('Diskussionstyp') }}
                        </label>
                        <SelectDiscussionType
                            id="select-discussion-type"
                            :options="availableTypes"
                            v-model="searchForm.types"
                            multiple
                        />
                    </div>
                    <div>
                        <label for="select-tags-input">
                            {{ $gettext('Schlagworte') }}
                        </label>
                        <SelectTagsInput
                            id="select-tags-input"
                            :options="availableTags"
                            v-model="searchForm.tags"
                            multiple
                        />
                    </div>
                    <div>
                        <label for="discussion-statuses-input">
                            {{ $gettext('Status der Diskussion') }}
                        </label>
                        <StudipSelect
                            id="discussion-statuses-input"
                            :options="discussionStatuses"
                            :placeholder="$gettext('Auswählen')"
                            v-model="searchForm.status"
                        >
                            <template #no-options>
                                <div>
                                    {{ $gettext('Es gibt keine Diskussionsstatus.') }}
                                </div>
                            </template>
                        </StudipSelect>
                    </div>
                    <div class="date-inputs-container">
                        <label class="flex-1">
                            {{ $gettext('Von') }}
                            <input type="date" v-model="searchForm.begin" autocomplete="off" />
                        </label>
                        <label class="flex-1">
                            {{ $gettext('Bis') }}
                            <input type="date" v-model="searchForm.end" autocomplete="off" />
                        </label>
                    </div>
                    <div>
                        <label for="select-user-input">
                            {{ $gettext('Autor/-in') }}
                        </label>
                        <SelectUserInput
                            id="select-user-input"
                            :options="courseMembers"
                            multiple
                            v-model="searchForm.authors"
                        />
                    </div>
                </div>
            </div>
        </form>

        <div class="search-result-container">
            <DiscussionIndex :discussions="discussions" :isLoading="isLoading" redirect="search">
                <template #pagination>
                    <tfoot v-if="pagination && pagination.total > pagination.limit">
                    <tr>
                        <td colspan="7">
                            <StudipPagination
                                :currentPage="pagination.currentPage"
                                :totalItems="pagination.total"
                                :itemsPerPage="pagination.limit"
                                @pageUpdated="fetchDiscussions" />
                        </td>
                    </tr>
                    </tfoot>
                </template>
            </DiscussionIndex>
        </div>
    </ForumApp>
</template>
