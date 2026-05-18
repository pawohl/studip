<script setup>
import {$gettext} from '@/assets/javascripts/lib/gettext';
import StudipDateTime from '@/vue/components/StudipDateTime.vue';
import UserAvatarDropdown from '@/vue/components/avatar/UserAvatarDropdown.vue';
import {userProfileURL} from '@/vue/components/forum/helpers/urls';
import {computed, onMounted} from 'vue';
import {useSortable} from '@/vue/composables/useSortable';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';

const forumConfig = useForumConfig();

const props = defineProps({
    reactions: {
        type: Array,
        required: true
    },
    emoji: {
        type: String,
        default: 'all'
    }
});

const computedReactions = computed(() => {
    if (props.emoji === 'all') {
        return props.reactions;
    }

    return props.reactions.filter(({ emoji }) => emoji === props.emoji);
});


const getEmojiLabel = emoji => forumConfig.reactionEmojis.find((emojis) => emojis.value === emoji)?.label;

const {
    sortedData: sortedReactions,
    sortBy,
    getSortClass,
    getAriaSortString,
    getAriaSortLabel
} = useSortable(computedReactions);

onMounted(() => {
    sortBy('mkdate', 'desc');
});
</script>

<template>
    <table class="default forum-table --posts-reactors">
        <colgroup>
            <col style="width: 50px;">
            <col>
            <col>
        </colgroup>
        <thead>
            <tr class="sortable">
                <th scope="col">
                    <span class="sr-only">{{ $gettext('Benutzer') }}</span>
                </th>
                <th
                    scope="col"
                    :class="getSortClass('user.formatted_name')"
                    :aria-sort="getAriaSortString('user.formatted_name')"
                    :aria-label="getAriaSortLabel('user.formatted_name', $gettext('Name'))"
                >
                    <button
                        type="button"
                        class="as-link"
                        @click="sortBy('user.formatted_name')"
                        :title="$gettext('Nach Name sortieren')"
                        :aria-label="$gettext('Nach Name sortieren')"
                    >
                        {{ $gettext('Name') }}
                    </button>
                </th>
                <th
                    scope="col"
                    :class="getSortClass('mkdate')"
                    :aria-sort="getAriaSortString('mkdate')"
                    :aria-label="getAriaSortLabel('mkdate', $gettext('Datum'))"
                >
                    <button
                        type="button"
                        class="as-link"
                        @click="sortBy('mkdate')"
                        :title="$gettext('Nach Datum sortieren')"
                        :aria-label="$gettext('Nach Datum sortieren')"
                    >
                        {{ $gettext('Datum') }}
                    </button>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(reaction, index) in sortedReactions" :key="index">
                <td>
                    <div class="user-reaction">
                        <UserAvatarDropdown
                            size="30px"
                            v-if="reaction.user.id"
                            :user="{
                                id: reaction.user.id,
                                username: reaction.user.username,
                                name: reaction.user.formatted_name,
                                avatar_url: reaction.user.meta.avatar.medium
                            }"
                        />
                        <span class="emoji-icon" aria-hidden="true" v-html="reaction.emoji"></span>
                        <span class="sr-only">{{ getEmojiLabel(reaction.emoji) }}</span>
                    </div>
                </td>
                <td>
                    <a
                        v-if="reaction.user.id"
                        :href="userProfileURL(reaction.user.username)"
                        :title="$gettext('Zum Profil')"
                        :aria-label="$gettext('Zum Profil von %{name}', { name: reaction.user.formatted_name })"
                        class="author-name"
                    >
                        {{ reaction.user.formatted_name }}
                    </a>
                    <p v-else class="author-name">
                        {{ $gettext('Unbekannt') }}
                    </p>
                </td>
                <td>
                    <StudipDateTime :iso="reaction.mkdate" :relative="true" />
                </td>
            </tr>
        </tbody>
    </table>
</template>
