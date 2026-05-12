import {defineStore} from "pinia";
import {ref} from "vue";

export const useForumConfig = defineStore(
    'forum_config',
    () => {
        const allowGuestAccess  = ref(false);
        const isAdmin  = ref(false);
        const isModerator  = ref(false);
        const isTutor  = ref(false);
        const isCategoriesHidden  = ref(false);
        const anonymousPost  = ref(false);
        const tileLayout  = ref(true);
        const reactionEmojis  = ref([]);

         function toggleForumLayout() {
            tileLayout.value = !tileLayout.value;

            if (!allowGuestAccess.value) {
                const configId = `${STUDIP.USER_ID}_FORUM_TILE_LAYOUT`;

                const data = {
                    id: configId,
                    type: 'config-values',
                    attributes: { value: tileLayout.value }
                };

                STUDIP.jsonapi.PATCH(`config-values/${configId}`, { data: { data } });
            }
        }

        return {
            allowGuestAccess,
            isAdmin,
            isModerator,
            isCategoriesHidden,
            anonymousPost,
            tileLayout,
            isTutor,
            reactionEmojis,
            toggleForumLayout
        }
    }
)
