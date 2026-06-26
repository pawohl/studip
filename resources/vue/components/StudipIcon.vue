<template>
    <button v-if="renderAsButton"
            class="icon-button as-link"
            :type="type"
            v-bind="attrs"
    >
        <IconContent v-bind="iconAttrs" />
    </button>

    <IconContent v-else v-bind="allAttrs" />
</template>

<script lang="ts" setup>
import {
    computed,
    defineComponent,
    h,
    ref,
    useAttrs,
    watch,

    CSSProperties,
    PropType, mergeProps
} from 'vue';
import iconLoader, { CachedIcon } from '../../assets/javascripts/lib/icon-loader';

interface IconProps {
    ariaRole?: string;
    name?: string;
    role?: string;
    shape: string;
    size?: number | null;
    inline?: boolean;
    type?: 'button'|'reset'|'submit';
}

const props = withDefaults(defineProps<IconProps>(),{
    role: 'clickable',
    size: null,
    inline: false
});

const isSvg = ref(false);
const content = ref('');

const attrs = useAttrs();
const iconAttrs = computed(() => ({
    ariaRole: props.ariaRole,
    cssClass: cssClass.value,
    content: content.value,
    isSvg: isSvg.value,
    style: computedStyle.value,
}));
const allAttrs = computed(() => ({
    ...attrs,
    ...iconAttrs.value
}));

const cssClass = computed(() => [
    'studip-icon',
    props.inline ? 'studip-icon-inline' : '',
    `icon-role-${props.role}`,
    `icon-shape-${shapeName.value}`
]);

const computedStyle = computed(() => props.size
    ? { width: `${props.size}px`, height: `${props.size}px` }
    : {}
);

const shapeName = computed((): string => {
    const containsUrl = (shape: string): boolean => /\bhttps?:\/\/\S+/i.test(shape);
    return containsUrl(props.shape) ? (props.shape.split('/').pop()?.replace('.svg', '') ?? '') : props.shape;
});

watch(
    () => props.shape,
    async (shape) => {
        const icon: CachedIcon = await iconLoader.load(shape);
        isSvg.value = icon.isSvg;
        content.value = icon.content;
    },
    { immediate: true }
);
const renderAsButton = computed(() => props.type !== undefined || props.name !== undefined);

const IconContent = defineComponent({
    props: {
        isSvg: Boolean,
        content: String,
        ariaRole: String,
        cssClass: [String, Array],
        style: Object as PropType<CSSProperties>
    },
    setup(props, { attrs }) {
        return () => {
            const baseAttrs = mergeProps(attrs, {
                class: props.cssClass,
                role: props.ariaRole,
                style: props.style,
            });

            return props.isSvg
                ? h('div', {
                    innerHTML: props.content,
                    ...baseAttrs
                })
                : h('img', {
                    src: props.content,
                    ...baseAttrs
                });
        }
    }
});
</script>
