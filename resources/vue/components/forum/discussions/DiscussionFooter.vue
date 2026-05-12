<script setup>
import StudipDateTime from "../../StudipDateTime.vue";
import StudipIcon from "../../StudipIcon.vue";
import ForumMembers from "../ForumMembers.vue";
import {$gettext} from "../../../../assets/javascripts/lib/gettext";
import {numberFormatter} from "../../../../assets/javascripts/lib/number_formatter";
import {useForumConfig} from "../../../store/pinia/forum/ForumConfig";
import {computed} from "vue";

const postCreateForm = defineModel('postCreateForm');

const forumConfig = useForumConfig();
const props = defineProps({
    discussion: {
        type: Object,
        required: true
    },
    posts: {
        type: Array,
        required: true
    },
    readIndex: {
        type: Number,
        default: -1
    }
});

const recentActivity = computed(() => props.posts.at(-1)?.mkdate ?? props.discussion.mkdate);
const hasUnreadPost = computed(() => {
    return props.readIndex === 0 && props.posts.length > 1 && props.posts[1].author.id !== STUDIP.USER_ID;
});

const addPost = () => {
    document.getElementById(`new-post`)?.scrollIntoView({ behavior: 'smooth' });
    postCreateForm.value = true;
}
</script>

<template>
    <div class="discussion__footer">
        <div v-if="!forumConfig.allowGuestAccess && hasUnreadPost" class="post__unread">
        </div>
        <div class="discussion__status">
            <div class="flex items-start gap-20">
                <div class="text-center">
                    <p>{{ $gettext('Erstellt') }}</p>
                    <StudipDateTime :iso="discussion.mkdate" :date_only="true" />
                </div>
                <div class="text-center">
                    <p>{{ $gettext('Beiträge') }}</p>
                    <p>{{ posts.length }}</p>
                </div>
                <div class="text-center">
                    <p>{{ $gettext('Aufrufe') }}</p>
                    <p>{{ numberFormatter(discussion.view_count, 1) }}</p>
                </div>
                <div class="text-center">
                    <p>{{ $gettext('Aktivität') }}</p>
                    <StudipDateTime :iso="recentActivity" :relative="true" />
                </div>
            </div>
            <ForumMembers :members="discussion.members" :limit="5" size="35px" />
            <button
                v-if="!forumConfig.allowGuestAccess && !discussion.closed_at"
                type="button"
                class="button button--icon-label"
                @click="addPost"
                :title="$gettext('Antworten')"
                :aria-label="$gettext('Antworten')"
                :class="{
                    'disabled': postCreateForm
                }"
            >
                <StudipIcon shape="reply2" :size="20" aria-hidden="true" />
                {{ $gettext('Antworten') }}
            </button>
        </div>
    </div>
</template>
