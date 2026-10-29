<script setup>
import { ref, computed, onMounted } from 'vue';
import { ClassicEditor, BalloonEditor } from '../../assets/javascripts/chunks/wysiwyg.js';

const props = defineProps({
    editorType: {
        type: String,
        validator: value => ['classic', 'balloon'].includes(value),
        default: 'classic'
    },
    name: {
        type: String,
        default: 'content'
    },
    id: {
        type: String,
        default: 'content-input'
    },
    autofocus: Boolean
});

const content = defineModel({ type: String, default: '' });

const createdEditor = ref(null);
const shouldFocus = ref(props.autofocus);

const editor = computed(() => {
    switch (props.editorType) {
        case 'classic':
            return ClassicEditor;
        case 'balloon':
            return BalloonEditor;
    }

    throw new Error('Unknown `editorType`');
});

const focus = () => {
    if (createdEditor.value && typeof createdEditor.value.focus === 'function') {
        createdEditor.value.focus();
    } else {
        shouldFocus.value = true;
    }
}

const onReady = editorInstance => {
    createdEditor.value = editorInstance;
    if (shouldFocus.value) {
        focus();
    }
    STUDIP.eventBus.emit('editor-loaded', createdEditor.value);
}

onMounted(() => STUDIP.loadChunk('mathjax'));
</script>

<template>
    <Ckeditor
        :editor="editor"
        :key="editorType"
        v-model="content"
        :onReady="onReady"
        v-bind="$attrs"
    />
    <textarea :id="id" :name="name" :value="content" style="display:none;"></textarea>
</template>
