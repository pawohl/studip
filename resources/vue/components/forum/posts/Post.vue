<script setup>
import {computed, onBeforeUnmount, onMounted, ref, useTemplateRef} from 'vue';
import PostEditForm from '@/vue/components/forum/posts/PostEditForm.vue';
import PostCreateForm from '@/vue/components/forum/posts/PostCreateForm.vue';
import PostContent from '@/vue/components/forum/posts/PostContent.vue';
import PostReactions from '@/vue/components/forum/posts/PostReactions.vue';
import {getDiscussionURL, userProfileURL} from '@/vue/components/forum/helpers/urls';
import StudipDateTime from '@/vue/components/StudipDateTime.vue';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import LinksPreview from '@/vue/components/LinksPreview.vue';
import UserAvatarDropdown from '@/vue/components/avatar/UserAvatarDropdown.vue';
import {useForumPost} from '@/vue/store/pinia/forum/ForumPost';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';

const forumConfig = useForumConfig();
const forumPostStore = useForumPost();
const props = defineProps({
    discussion: {
        type: Object,
        required: true,
    },
    post: {
        type: Object,
        required: true,
    },
    authUser: {
        type: Object,
        required: true
    },
    index: {
        type: Number,
        default: 0
    },
    readIndex: {
        type: Number,
        default: 0
    }
});

const postRef = useTemplateRef('postRef');
const postContentRef = useTemplateRef('postContent');
const userAvatarContainerRef = useTemplateRef('userAvatarContainer');

const selectedText = ref('');
const showPostEditForm = ref(false);
const showPostCreateForm = ref(false);

const isUnread = computed(() => (!props.post.author && props.index >= props.readIndex) || (props.index >= props.readIndex && props.post.author.id !== STUDIP.USER_ID))
const canEditPost = computed(() => forumConfig.isTutor || (props.post.author?.id === STUDIP.USER_ID && !props.discussion.closed_at));
const canDeletePost = computed(() => canEditPost.value);
const copyToClipboard = () => {
    if (selectedText.value) {
        navigator.clipboard.writeText(selectedText.value);
        postContentRef.value.removeSelection();
        STUDIP.Report.info($gettext('Der markierte Text wurde in die Zwischenablage kopiert.'));
    }
}

const editPost = () => {
    if (!canEditPost.value) {
        return;
    }

    document.getElementById(`post_${props.post.id}`)?.scrollIntoView({ behavior: 'smooth' });
    showPostEditForm.value = true;
}

const deletePost = async () => {
    if (!canDeletePost.value) {
        return;
    }

    STUDIP.Dialog.confirm(
        $gettext('Wollen Sie diesen Beitrag löschen?'),
        async () => {
            try {
                await STUDIP.jsonapi.withPromises().DELETE(`forum-postings/${props.post.id}`);
                forumPostStore.removePost(props.post.id);
                STUDIP.Report.success($gettext('Der Beitrag wurde gelöscht.'));
            } catch (error) {
                STUDIP.Report.error(error);
            }
        },
        STUDIP.Dialog.close()
    );
}

const addPost = () => {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    showPostCreateForm.value = false;
}

const addReply = () => {
    document.getElementById(`create_form_${props.post.id}`)?.scrollIntoView({ behavior: 'smooth' });
    showPostCreateForm.value = true;
    selectedText.value = props.post.content;
}

const forwardPost = () => {
    let messageBoyd = `
        ${$gettext('Die Sender:in dieser Nachricht möchte Sie auf den folgenden Beitrag aufmerksam machen: ')}
        <br />
        <br />
        ${$gettext('Link zum Beitrag: ')}
        <a href="${getDiscussionURL(props.discussion.discussion_id) + '#post_' + props.post.id}">
            ${props.discussion.title}
        </a>
    `;

    STUDIP.Dialog.fromURL(STUDIP.URLHelper.getURL('dispatch.php/messages/write'), {
        data: {
            default_subject: 'WG: ' + props.discussion.title,
            default_body: STUDIP.wysiwyg.markAsHtml(messageBoyd)
        },
        method: 'post'
    });
}

const removePostHighlight = id => {
    const element = document.getElementById(id);
    if (!element) {
        console.error('Element not found!');
        return;
    }
    element.classList.remove('post--highlight');
}

let postObserver = null;

onMounted(() => {
    postObserver = new IntersectionObserver(
        entries => entries.forEach(e => {
            if (e.isIntersecting) {
                forumPostStore.updateCurrentPostIndex(props.index);
            }
        }),{
            rootMargin: `-110px 0px -${document.documentElement.clientHeight - 120}px 0px`
        }
    );

    postObserver.observe(postRef.value);

    if (!forumConfig.allowGuestAccess && isUnread.value && forumPostStore.firstUnreadPostIndex < 0) {
        forumPostStore.updateFirstUnreadPostIndex(props.index);
    }
});

onBeforeUnmount(() => postObserver.disconnect());
</script>

<template>
    <div
        ref="postRef"
        :id="`post_${post.id}`"
        class="post"
        :data-index="index"
        @click="removePostHighlight(`post_${post.id}`)"
    >
        <div v-if="!forumConfig.allowGuestAccess && isUnread" class="post__unread">
            <span class="sr-only">{{ $gettext('Ungelesen') }}</span>
        </div>
        <div class="post__body">
            <div class="post__author">
                <div class="post__author-avatar" ref="userAvatarContainer">
                    <UserAvatarDropdown
                        v-if="post.author?.id"
                        :user="post.author"
                        size="50px"
                        @update:modelValue="state => {
                            if (state) userAvatarContainerRef.style.setProperty('z-index', 100);
                            else userAvatarContainerRef.style.setProperty('z-index', 1);
                        }"
                    />
                </div>
                <div class="post__author-name-container --xs">
                    <p v-if="!post.author" class="author-name">
                        {{ $gettext('Unbekannt') }}
                    </p>
                    <p v-else-if="!post.author.id" class="author-name">
                        {{ $gettext('Anonym') }}
                    </p>
                    <a
                        v-else
                        :href="userProfileURL(post.author.username)"
                        :title="$gettext('Zum Profil')"
                        :aria-label="$gettext('Zum Profil von %{name}', { name: post.author.name })"
                        class="author-name"
                    >
                        {{ post.author.name }}
                    </a>
                    <span v-if="post.chdate > post.mkdate">
                        {{ $gettext('Bearbeitet: ') }}
                        <StudipDateTime :iso="post.chdate" :relative="true" />
                    </span>
                    <StudipDateTime v-else :iso="post.mkdate" :relative="true" />
                </div>
            </div>
            <div class="post__content">
                <div class="post__author-name-container --xl">
                    <p v-if="!post.author" class="author-name">
                        {{ $gettext('Unbekannt') }}
                    </p>
                    <p v-else-if="!post.author.id" class="author-name">
                        {{ $gettext('Anonym') }}
                    </p>
                    <a
                        v-else
                        :href="userProfileURL(post.author.username)"
                        :title="$gettext('Zum Profil')"
                        :aria-label="$gettext('Zum Profil von %{name}', { name: post.author.name })"
                        class="author-name"
                    >
                        {{ post.author.name }}
                    </a>
                    <span v-if="post.chdate > post.mkdate">
                        {{ $gettext('Bearbeitet: ') }}
                        <StudipDateTime :iso="post.chdate" :relative="true" />
                    </span>
                    <StudipDateTime v-else :iso="post.mkdate" :relative="true" />
                </div>
                <template v-if="showPostEditForm">
                    <PostEditForm :post="post" :authUser="authUser" class="mt-10" @canceled="showPostEditForm = false" @updated="showPostEditForm = false" />
                </template>
                <template v-else>
                    <div class="post__text">
                        <PostContent
                            ref="postContent"
                            v-model="selectedText"
                            :content="post.content_html"
                            class="forum-quote"
                        >
                            <template #actions>
                                <a
                                    :href="`#create_form_${post.id}`"
                                    class="ballon-action__button"
                                    v-if="!forumConfig.allowGuestAccess && !showPostCreateForm && !discussion.closed_at"
                                    @click="showPostCreateForm = true; postContentRef.removeSelection()"
                                    :title="$gettext('Auswahl zitieren und antworten')"
                                    :aria-label="$gettext('Auswahl zitieren und antworten')"
                                >
                                    <StudipIcon shape="quote" :size="20" />
                                </a>
                                <button
                                    type="button"
                                    class="ballon-action__button button-base"
                                    @click="copyToClipboard"
                                    :title="$gettext('Kopieren')"
                                    :aria-label="$gettext('Kopieren')"
                                >
                                    <StudipIcon shape="clipboard" :size="20" />
                                </button>
                            </template>
                        </PostContent>
                    </div>

                    <div v-if="post.meta.opengraph_urls.length" class="opengraph-urls">
                        <LinksPreview :links="post.meta.opengraph_urls" />
                    </div>

                    <PostReactions :posting_id="post.id" :postReactions="post.reactions" />
                </template>

                <div class="post__footer">
                    <div></div>
                    <div class="inline-flex items-center gap-40">
                        <div v-if="!forumConfig.allowGuestAccess" class="inline-flex items-center gap-10">
                            <button
                                v-if="canEditPost"
                                @click="editPost"
                                type="button"
                                class="button button--icon-only"
                                :class="{
                                    'disabled': showPostEditForm
                                }"
                                :title="$gettext('Beitrag bearbeiten')"
                                :aria-label="$gettext('Beitrag bearbeiten')"
                            >
                                <StudipIcon shape="edit" :size="20" aria-hidden="true" />
                            </button>
                            <button
                                v-if="canDeletePost"
                                @click="deletePost"
                                type="button" class="button button--icon-only"
                                :title="$gettext('Beitrag löschen')"
                                :aria-label="$gettext('Beitrag löschen')"
                            >
                                <StudipIcon shape="trash" :size="20" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                @click="forwardPost"
                                class="button button--icon-only"
                                :title="$gettext('Beitrag weiterleiten')"
                                :aria-label="$gettext('Beitrag weiterleiten')">
                                <StudipIcon shape="export" :size="20" aria-hidden="true" />
                            </button>
                            <button
                                v-if="!discussion.closed_at"
                                @click="addReply"
                                type="button"
                                class="button button--icon-only"
                                :class="{
                                    'disabled': showPostCreateForm
                                }"
                                :title="$gettext('Zitieren und antworten')"
                                :aria-label="$gettext('Zitieren und Antworten')"
                            >
                                <StudipIcon shape="quote" :size="20" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showPostCreateForm && !discussion.closed_at" :id="`create_form_${post.id}`" class="post-form-container" style="scroll-margin-top: 200px;">
        <PostCreateForm
            :parentPost="post"
            :discussionId="props.discussion.discussion_id"
            :authUser="authUser"
            v-model:quote="selectedText"
            @canceled="showPostCreateForm = false; selectedText = ''"
            @created="addPost"
        />
    </div>
</template>
