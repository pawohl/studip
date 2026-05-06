<template>

    <studip-message-box v-if="isInContext"
                        type="warning"
                        :hideClose="true"
    >
        {{ $gettext('Der Link verweist auf den Inhalt einer zugangsbeschränkten Veranstaltung und ist '
            + 'eventuell nicht für alle Personen im System erreichbar.') }}
    </studip-message-box>

    <form class="default" v-if="editing !== null">
        <LabelRequired
            id="short-url-path"
            :label="$gettext('Zielseite')"
        >
            <input type="text"
                   name="path"
                   id="short-url-path"
                   v-model="editing.attributes.path"
                   readonly
            />
        </LabelRequired>

        <LabelRequired
            id="short-url-alias"
            :label="$gettext('Kürzel')"
        >
            <input
                type="text"
                name="alias"
                id="short-url-alias"
                v-model="editing.attributes.alias"
                @input="validateAlias"
                maxlength="255"
                @keydown.enter="triggerSave"
                ref="linkAlias"
            />
        </LabelRequired>

        <LabelRequired
            id="short-url-title"
            :label="$gettext('Titel des Linkziels')"
        >
            <input
                type="text"
                name="title"
                id="short-url-title"
                v-model="editing.attributes.title"
                maxlength="255"
                @keydown.enter="triggerSave"
            />
        </LabelRequired>
    </form>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {$gettext} from "../../../assets/javascripts/lib/gettext";
import StudipMessageBox from '../../components/StudipMessageBox';
import LabelRequired from '../../components/forms/LabelRequired';

const props = defineProps({
    shortLink: {
        type: Object,
        required: true
    },
    isInContext: {
        type: Boolean,
        default: false
    }
});
const emit = defineEmits(['save']);
const editing = ref(props.shortLink);
const linkAlias = ref(null);

function triggerSave() {
    emit('save', editing.value);
}

function validateAlias() {
    const pattern = /[^a-zA-Z0-9-]/g;
    editing.value.attributes.alias = editing.value.attributes.alias.replace(pattern, '').slice(0, 256);
}

onMounted(() => {
    linkAlias.value.focus();
});

</script>
