<template>
    <li v-if="item.visible" class="navigation-item" :class="{ 'navigation-item-active': active }">
        <template v-if="hasChildren()">
            <div class="navigation-title">
                <a
                    :href="url"
                    :title="navigateToText(item.title)"
                    :aria-label="navigateToText(item.title)"
                    tabindex="0"
                >
                    <span class="navigation-icon">
                        <studip-icon v-if="item.icon" :shape="item.icon" :size="24" :class="{avatar: item.avatar}" />
                    </span>
                    <span class="navigation-text">
                        {{ item.title }}
                    </span>
                </a>
            </div>
            <button
                class="styleless navigation-in"
                :title="openNavigationText(item.title)"
                :aria-label="openNavigationText(item.title)"
                @click="moveTo(item.path)"
                @keydown.prevent.enter="moveTo(item.path)"
                @keydown.prevent.space="moveTo(item.path)"
            >
                <studip-icon shape="arr_1right" role="info_alt" alt=""></studip-icon>
            </button>
        </template>
        <div v-else class="navigation-title">
            <form v-if="item.button"
                  :action="url"
                  method="post"
            >
                <button class="as-link"
                        tabindex="0"
                        :title="navigateToText(item.title)"
                        :aria-label="navigateToText(item.title)"
                >
                    <studip-icon v-if="item.icon" :shape="item.icon" :size="24" :class="{avatar: item.avatar}" />
                    {{ item.title }}
                </button>
            </form>
            <a v-else
                :href="url"
                tabindex="0"
                :title="navigateToText(item.title)"
                :aria-label="navigateToText(item.title)"
            >
                <studip-icon v-if="item.icon" :shape="item.icon" :size="24" :class="{avatar: item.avatar}" />
                {{ item.title }}
            </a>
        </div>
    </li>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import StudipIcon from '../StudipIcon.vue';

export default defineComponent({
    name: 'NavigationItem',
    components: { StudipIcon },
    props: {
        item: {
            type: Object,
            required: true,
        },
        active: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        url(): string {
            return window.STUDIP.URLHelper.getURL(this.item.url);
        }
    },
    methods: {
        moveTo(path: string) {
            window.STUDIP.eventBus.emit('responsive-navigation-move-to', path);
        },
        hasChildren() {
            return this.item.children && Object.keys(this.item.children).length > 0;
        },
        navigateToText(title: string) {
            return this.$gettext(
                'Navigiere zu %{title}',
                { title }
            );
        },
        openNavigationText(title: string): string {
            return this.$gettext(
                'Unternavigation zu %{title} öffnen',
                { title }
            );
        },
    },
});
</script>
