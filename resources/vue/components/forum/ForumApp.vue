<script setup>
import {onMounted} from 'vue';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';

const CSRF = STUDIP.CSRF_TOKEN;
const forumConfig = useForumConfig();
const fetchConfigs = async () => {
    try {
        const response = await STUDIP.jsonapi.withPromises().GET(`courses/${STUDIP.URLHelper.parameters.cid}/forum-configs`);

        forumConfig.$patch({
            isModerator: response.meta['is-moderator'],
            isAdmin: response.meta['is-admin'],
            isTutor: response.meta['is-tutor'],
            isCategoriesHidden: response.meta['is-categories-hidden'],
            anonymousPost: response.meta['anonymous-post'],
            tileLayout: response.meta['tile-layout'],
            reactionEmojis: response.meta['reaction-emojis']
        });
    } catch (error) {
        STUDIP.Report.error(error);
    }
}

onMounted(async () => {
    if (STUDIP.USER_ID === 'nobody') {
        forumConfig.$patch({
            allowGuestAccess: true
        });
    } else {
        await fetchConfigs();
    }
});
</script>

<template>
    <div class="forum">
        <div class="forum__container use-utility-classes">
            <div>
                <slot />
            </div>
            <div class="forum__sidebar">
                <slot name="sidebar" />
            </div>
        </div>

        <form id="forum-delete-form" method="post">
            <input type="hidden" :name="CSRF.name" :value="CSRF.value" />
        </form>
    </div>
</template>
