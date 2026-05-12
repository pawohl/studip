<script setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';
import {useForumPost} from '@/vue/store/pinia/forum/ForumPost';
import StudipIcon from '@/vue/components/StudipIcon.vue';
import StudipSwitch from '@/vue/components/StudipSwitch.vue';
import StudipWysiwyg from '@/vue/components/StudipWysiwyg.vue';
import {deserializeJSONAPIResponse} from '@/assets/javascripts/lib/jsonapiUtils';
import {userProfileURL} from '@/vue/components/forum/helpers/urls';

const forumConfig = useForumConfig();
const forumDiscussionPost = useForumPost();
const emit = defineEmits(['canceled', 'created', 'update:quote']);
const props = defineProps({
    discussionId: {
        type: String,
        required: true,
    },
    authUser: {
        type: Object,
        required: true
    },
    quote: {
        type: String,
    },
    parentPost: {
        type: Object,
        default: () => ({})
    }
});

const normalizeQuote = quote => {
    const parser = new DOMParser();
    const document = parser.parseFromString(quote, 'text/html');

    const blockquotes = document.querySelectorAll('blockquote');
    blockquotes.forEach(bq => {
        const replacement = document.createElement('div');
        replacement.innerHTML = '[...]<br />';
        bq.parentNode.replaceChild(replacement, bq);
    });

    return document.body.innerHTML;
}

const quoteAuthor = computed(() => {
    if (props.parentPost.anonymous) {
        return $gettext('Jemand');
    }

    return props.parentPost.author.name;
});

onMounted(() => {
    if (window.location.hash) {
        window.history.pushState('', document.title, window.location.href.split('#')[0]);
    }

    if (props.quote) {
        content.value = `
            <blockquote>
                <div>
                    <a href="#post_${props.parentPost.id}">
                        <b>${quoteAuthor.value} ${$gettext('hat geschrieben: ')}</b>
                    </a>
                </div>
                ${normalizeQuote(props.quote)}
            </blockquote>
            <br />
        `;
    }
})

onUnmounted(() => {
    if (window.location.hash) {
        window.history.pushState('', document.title, window.location.href.split('#')[0]);
    }
})

const content = ref('');
const anonymous = ref(false);
const isLoading = ref(false);

const getPostJSONAPIObject = () => ({
    data: {
        type: 'forum-postings',
        attributes: {
            content: content.value,
            anonymous: anonymous.value,
        },
        relationships: {
            discussion: {
                data: {
                    type: 'forum-discussions',
                    id: props.discussionId
                }
            },
            posting: {
                data: {
                    type: 'forum-postings',
                    id: props.parentPost.id
                }
            }
        }
    }
})

const storePost = async () => {
    try {
        isLoading.value = true;

        const response = await STUDIP.jsonapi.withPromises().POST(
            'forum-postings?include=author,opengraph-urls,posting,reactions,reactions.user&fields[users]=id',
            { data: getPostJSONAPIObject }
        );

        const post = await deserializeJSONAPIResponse(response);

        forumDiscussionPost.addPost(post);
        content.value = "";
        emit("created", post);

        STUDIP.Report.success($gettext("Der Beitrag wurde gespeichert."));
    } catch (error) {
        STUDIP.Report.error(error);
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <form @submit.prevent="storePost" class="default post-form forum-quote">
        <div class="post-form__author">
            <a
                class="post-form__author-image profile-image-container"
                :href="userProfileURL(authUser.username)"
                :title="$gettext('Zum Profil')"
                :aria-label="$gettext('Zum Profil von %{name}', { name: authUser.name })"
            >
                <img :src="authUser.avatar_url" :alt="authUser.name" />
            </a>
            <p class="post-form__author-name">{{ authUser.name }}</p>
        </div>
        <label for="post-content">
            <span class="sr-only">{{ $gettext('Inhalt') }}</span>
        </label>
        <StudipWysiwyg id="post-content" :required="true" v-model="content" />
        <div v-if="forumConfig.anonymousPost" class="mt-10">
            <StudipSwitch name="anonymous" v-model="anonymous" :label="$gettext('Anonym')" />
        </div>
        <div class="post-form__footer">
            <button
                type="submit"
                :disabled="isLoading || !content"
                class="button button--icon-label"
            >
                <StudipIcon shape="accept" :size="20" aria-hidden="true" />
                {{ $gettext('Beitrag erstellen') }}
            </button>
            <button
                type="button"
                class="button button--icon-label"
                @click="$emit('canceled')"
            >
                <StudipIcon shape="decline" :size="20" aria-hidden="true" />
                {{ $gettext('Abbrechen') }}
            </button>
        </div>
    </form>
</template>
