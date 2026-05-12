<script setup>
import {computed, onMounted, ref} from 'vue';
import CreateTopic from '@/vue/components/forum/topics/CreateTopic.vue';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import ForumApp from '@/vue/components/forum/ForumApp.vue';
import TopicsIndex from '@/vue/components/forum/topics/TopicsIndex.vue';
import {deserializeJSONAPIResponse} from '@/assets/javascripts/lib/jsonapiUtils';
import StudipPagination from '@/vue/components/StudipPagination.vue';
import EmptyForum from '@/vue/components/forum/EmptyForum.vue';

const forumConfig = useForumConfig();

const toggleLayoutMessage = computed(() => {
    if (forumConfig.tileLayout) {
        return $gettext('Kachelansicht aktiviert');
    }

    return $gettext('Tabellarische Ansicht aktiviert');
});

const topics = ref([]);
const isLoading = ref(false);
const pagination = ref({});
const fetchTopics = async (_, offset = 0) => {
    try {
        isLoading.value = true;

        const response = await STUDIP.jsonapi.withPromises().GET(
            `courses/${STUDIP.URLHelper.parameters.cid}/forum-topics`,
            { data: { include: 'category', page: { offset } } }
        );

        pagination.value = {
            ...response.meta.page,
            currentPage: response.meta.page.offset / response.meta.page.limit,
            links: response.links
        };

        topics.value = await deserializeJSONAPIResponse(response);
    } catch (error) {
        STUDIP.Report.error(error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(async () => {
    await fetchTopics();
});
</script>

<template>
    <ForumApp>
        <EmptyForum v-if="topics.length === 0 && !isLoading" />
        <template v-else>
            <header class="header">
                <div class="header__content header__content--with-actions">
                    <h2>
                        {{ $gettext('Themen') }}
                    </h2>
                    <div class="actions">
                        <CreateTopic />
                        <button
                            type="button"
                            class="button button--icon-only"
                            v-if="forumConfig.tileLayout"
                            @click="forumConfig.toggleForumLayout();"
                            :title="$gettext('Tabellarische Ansicht')"
                            :aria-label="$gettext('Tabellarische Ansicht')"
                        >
                            <StudipIcon shape="view-list" :size="20" aria-hidden="true" />
                        </button>
                        <button
                            v-else
                            type="button"
                            class="button button--icon-only"
                            @click="forumConfig.toggleForumLayout();"
                            :title="$gettext('Kachelansicht')"
                            :aria-label="$gettext('Kachelansicht')"
                        >
                            <StudipIcon shape="view-wall" :size="20" aria-hidden="true" />
                        </button>
                        <div aria-live="polite" class="sr-only" role="status">{{ toggleLayoutMessage }}</div>
                    </div>
                </div>
            </header>
            <div class="py-10">
                <TopicsIndex :topics="topics" :isLoading="isLoading" :showEmptyForumLayout="true">
                    <template #pagination>
                        <StudipPagination
                            v-if="pagination.total > pagination.limit"
                            :currentPage="pagination.currentPage"
                            :totalItems="pagination.total"
                            :itemsPerPage="pagination.limit"
                            @pageUpdated="fetchTopics" />
                    </template>
                </TopicsIndex>
            </div>
        </template>
    </ForumApp>
</template>
