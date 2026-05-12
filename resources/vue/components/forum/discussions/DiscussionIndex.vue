<script setup>
import {onMounted, toRef} from 'vue';
import {getDiscussionURL, getSearchURL} from "../helpers/urls";
import {numberFormatter} from '@/assets/javascripts/lib/number_formatter';
import ForumMembers from '@/vue/components/forum/ForumMembers.vue';
import {useSortable} from '@/vue/composables/useSortable';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import StudipDateTime from '@/vue/components/StudipDateTime.vue';
import StudipActionMenu from '@/vue/components/StudipActionMenu.vue';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import Loader from '../Loader.vue';

const forumConfig = useForumConfig();
const props = defineProps({
    discussions: {
        type: Array,
        required: true
    },
    withActions: {
        type: Boolean,
        default: true
    },
    redirect: {
        type: String,
        default: 'topic'
    },
    isLoading: {
        type: Boolean,
        default: false
    }
});

const discussionsRef = toRef(props, 'discussions');

const {
    sortedData,
    sortBy,
    getSortClass,
    getAriaSortString,
    getAriaSortLabel
} = useSortable(discussionsRef);

const getActionMenusItems = discussion => {
    if (forumConfig.isModerator || discussion.user?.id === STUDIP.USER_ID) {
        return [
            { label: $gettext('Bearbeiten'),  icon: 'edit', emit: 'edit'},
            { label: $gettext('Löschen'),  icon: 'trash', emit: 'delete'}
        ];
    }

    return [];
}

const editDiscussion = id => STUDIP.Dialog.fromURL(
    STUDIP.URLHelper.getURL(`dispatch.php/course/forum/discussions/edit/${id}`),
    {
        width: '900'
    }
);

const showConfirmDelete = id => STUDIP.Dialog.confirm(
    $gettext('Wollen Sie diese Diskussion löschen? Damit werden auch alle Beiträge gelöscht!'),
    () => deleteDiscussion(id),
    STUDIP.Dialog.close()
);

const deleteDiscussion = id => {
    const deleteForm = document.getElementById('forum-delete-form');
    deleteForm.action = STUDIP.URLHelper.getURL(`dispatch.php/course/forum/discussions/delete/${id}`);
    deleteForm.submit();
}

onMounted(() => {
    sortBy('meta.recent_activity', 'desc');
});
</script>

<template>
    <table class="default forum-table forum-table--discussions-index">
        <colgroup>
            <col>
            <col style="width: 15%;">
            <col style="width: 10%;">
            <col style="width: 10%;">
            <col style="width: 15%;">
            <col style="width: 5%">
            <col v-if="withActions" style="width: 10%">
        </colgroup>
        <thead>
            <tr class="sortable">
                <th
                    scope="col"
                    :class="getSortClass('title')"
                    :aria-sort="getAriaSortString('title')"
                    :aria-label="getAriaSortLabel('title', $gettext('Diskussionstitel'))"
                >
                    <button
                        type="button"
                        class="as-link"
                        @click="sortBy('title')"
                        :title="$gettext('Nach Diskussionstitel sortieren')">
                        {{ $gettext('Diskussion') }}
                    </button>
                </th>
                <th
                    scope="col"
                    :class="getSortClass('members')"
                    :aria-sort="getAriaSortString('members')"
                    :aria-label="getAriaSortLabel('members', $gettext('Anzahl der Teilnehmenden'))"
                >
                    <button
                        type="button"
                        class="as-link"
                        @click="sortBy('members')"
                        :title="$gettext('Nach Anzahl der Teilnehmenden sortieren')">
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
                        :title="$gettext('Nach Anzahl der Beiträge sortieren')">
                        {{ $gettext('Beiträge') }}
                    </button>
                </th>
                <th
                    scope="col"
                    :class="getSortClass('view_count')"
                    :aria-sort="getAriaSortString('view_count')"
                    :aria-label="getAriaSortLabel('view_count', $gettext('Anzahl der Aufrufe'))"
                >
                    <button
                        type="button"
                        class="as-link"
                        @click="sortBy('view_count')"
                        :title="$gettext('Nach Anzahl der Aufrufe sortieren')">
                        {{ $gettext('Aufrufe') }}
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
                        :title="$gettext('Nach letzter Aktivität sortieren')">
                        {{ $gettext('Letzte Aktivität') }}
                    </button>
                </th>
                <th scope="col">
                    <span class="sr-only">{{ $gettext('Status') }}</span>
                </th>
                <th scope="col" v-if="withActions">
                    <span class="sr-only">{{ $gettext('Aktionen') }}</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-if="isLoading" >
                <td colspan="7">
                    <Loader />
                </td>
            </tr>
            <template v-else>
                <tr v-for="discussion in sortedData" :key="discussion.id">
                    <td>
                        <div class="table-row-overview">
                            <div class="title-with-actions">
                                <div class="title-with-actions__content">
                                    <StudipIcon class="icon" v-if="discussion.sticky" shape="pin" role="info" :size="20" />
                                    <a
                                        class="title-with-actions__link"
                                        :href="getDiscussionURL(discussion.id, {redirect})"
                                        :title="$gettext('Zur Diskussion')">
                                        <span class="title-with-actions_title discussion-title line-clamp-2 m-0">{{ discussion.title }}</span>
                                        <template v-if="!forumConfig.allowGuestAccess">
                                            <span
                                                v-if="redirect !== 'recent' && discussion.meta.unread_postings_count"
                                                class="unread-items-badge"
                                                role="status"
                                                aria-live="polite"
                                                :aria-label="$gettext('Sie haben %{count} ungelesene Beiträge.', {count: discussion.meta.unread_postings_count})"
                                                :title="$gettext('Sie haben %{count} ungelesene Beiträge.', {count: discussion.meta.unread_postings_count})"
                                            >
                                                {{ discussion.meta.unread_postings_count }}
                                            </span>
                                            <span
                                                v-if="redirect === 'recent' && discussion.meta.recent_postings_count"
                                                class="unread-items-badge"
                                                role="status"
                                                aria-live="polite"
                                                :aria-label="$gettext('%{count} neue Beiträge seit Ihrem letzten Besuch.', {count: discussion.meta.recent_postings_count})"
                                                :title="$gettext('%{count} neue Beiträge seit Ihrem letzten Besuch.', {count: discussion.meta.recent_postings_count})"
                                            >
                                                {{ discussion.meta.recent_postings_count }}
                                            </span>
                                        </template>
                                    </a>
                                </div>
                                <div class="title-with-actions__actions-xs">
                                    <StudipActionMenu
                                        v-if="withActions"
                                        :context="discussion.title"
                                        :items="getActionMenusItems(discussion)"
                                        @edit="editDiscussion(discussion.id)"
                                        @delete="showConfirmDelete(discussion.id)"
                                    />
                                </div>
                            </div>
                            <div class="inline-flex gap-10 items-start mb-10 mt-10">
                                <div v-if="discussion.category && !forumConfig.isCategoriesHidden" class="discussion-category">
                                    <span v-if="discussion.category.color" :style="{ width: '12px', height: '12px', backgroundColor: discussion.category.color }"></span>
                                    <p class="m-0">
                                        {{ discussion.category.name }}
                                    </p>
                                </div>
                                <ul class="tags-container">
                                    <li v-if="discussion.discussion_type?.name" class="tags-container__tag">
                                        <StudipIcon :shape="discussion.discussion_type.icon" :size="12" :title="discussion.discussion_type.name" role="info" />
                                    </li>
                                    <template v-for="tag in discussion.tags" :key="tag.id">
                                        <li class="tags-container__tag">
                                            <a :href="getSearchURL(`tag_ids[]=${tag.id}`)" :title="$gettext('Zum Schlagwort')" :aria-label="$gettext('Zum Schlagwort')">
                                                {{ '#'+tag.name }}
                                            </a>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <!--mobile display: start-->
                        <div class="details-xs mt-10">
                            <dl>
                                <dt>{{ $gettext('Aufrufe') }}</dt>
                                <dd class="inline-flex gap-5 items-center">
                                    <StudipIcon shape="block-eyecatcher" :size="15" role="info" aria-hidden="true" />
                                    {{ numberFormatter(discussion.view_count, 1) }}
                                </dd>

                                <dt>{{ $gettext('Anzahl der Beiträge') }}</dt>
                                <dd class="inline-flex gap-5 items-center">
                                    <StudipIcon shape="post" :size="15" role="info" aria-hidden="true" />
                                    {{ discussion.meta.postings_count }}
                                </dd>

                                <dt>{{ $gettext('Letzte Aktivität') }}</dt>
                                <dd class="inline-flex gap-5 items-center">
                                    <StudipIcon shape="activity" :size="15" role="info" aria-hidden="true" />
                                    <StudipDateTime :iso="discussion.meta.recent_activity" :relative="true" />
                                </dd>

                                <dt>{{ $gettext('Ist geschlossen') }}</dt>
                                <dd
                                    class="inline-flex gap-5 items-center"
                                >
                                    <StudipIcon
                                        v-if="discussion.closed_at"
                                        :title="$gettext('Diskussion ist geschlossen')"
                                        shape="lock-locked2"
                                        :size="15"
                                        role="inactive"
                                        aria-hidden="true"
                                    />
                                    <span class="sr-only">
                                        {{ discussion.closed_at ? $gettext('Diskussion ist geschlossen') : $gettext('Diskussion ist offen') }}
                                    </span>
                                </dd>
                            </dl>

                            <dl>
                                <dt>{{ $gettext('Teilnehmende') }}</dt>
                                <dd class="nowrap">
                                    <ForumMembers :members="discussion.members" :limit="5" />
                                </dd>
                            </dl>
                        </div>
                        <!--mobile display: end-->
                    </td>
                    <td class="nowrap">
                        <ForumMembers :members="discussion.members" :limit="5" />
                    </td>
                    <td>
                        {{ discussion.meta.postings_count }}
                    </td>
                    <td>
                        {{ numberFormatter(discussion.view_count, 1) }}
                    </td>
                    <td class="nowrap">
                        <StudipDateTime v-if="discussion.meta.recent_activity" :iso="discussion.meta.recent_activity" :relative="true" />
                        <template v-else>{{ $gettext('Keine Aktivität') }}</template>
                    </td>
                    <td class="text-center">
                        <StudipIcon
                            v-if="discussion.closed_at"
                            :title="$gettext('Diskussion ist geschlossen')"
                            shape="lock-locked2"
                            :size="20"
                            aria-hidden="true"
                            role="inactive" />

                        <span role="status" class="sr-only">
                            {{ discussion.closed_at ? $gettext('Diskussion ist geschlossen') : $gettext('Diskussion ist offen') }}
                        </span>
                    </td>
                    <td v-if="withActions" class="actions">
                        <StudipActionMenu
                            :context="discussion.title"
                            :items="getActionMenusItems(discussion)"
                            @edit="editDiscussion(discussion.id)"
                            @delete="showConfirmDelete(discussion.id)"
                        />
                    </td>
                </tr>
                <tr v-if="sortedData.length === 0">
                    <td v-if="!forumConfig.isLoading" colspan="7">
                        {{ $gettext('Es sind noch keine Diskussionen vorhanden.') }}
                    </td>
                </tr>
            </template>
        </tbody>
        <slot name="pagination" />
    </table>
</template>
