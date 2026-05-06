<template>
    <button class="in-navigation as-link"
            @click.prevent="openDialog()"
            :title="$gettext('Link zu dieser Seite erstellen')"
    >
        <studip-icon v-if="withIcon" shape="share" role="info_alt"/>
        <template v-else>
            {{ $gettext('Link zur Seite') }}
        </template>
    </button>
    <studip-dialog
        v-if="showDialog"
        :title="$gettext('Link zur Seite erzeugen')"
        :confirmText="$gettext('Link kopieren & speichern')"
        confirmClass="accept"
        :closeText="$gettext('Abbrechen')"
        closeClass="cancel"
        :height="isInContext ? 420 : 360"
        @close="closeDialog"
        @confirm="save"
    >
        <template #dialogContent>
            <short-url
                :shortLink="newLink"
                :isInContext="isInContext"
                @save="save"
            />
        </template>
    </studip-dialog>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import {$gettext} from "../../../assets/javascripts/lib/gettext";
import ShortUrl from './ShortUrl';
import {useShortUrlsStore} from '../../store/pinia/shortUrlsStore';
import Sqids from "sqids";

defineProps({
    isInContext: {
        type: Boolean,
        default: false
    },
    withIcon: {
        type: Boolean,
        default: false
    }
});

const store = useShortUrlsStore();
const showDialog = ref(false);
const newLink = ref(null);

function openDialog() {
    showDialog.value = true;
}

async function save() {
    const data = await store.storeShortUrl(newLink.value);

    navigator.clipboard.writeText(STUDIP.URLHelper.getURL(data.meta['alias-link'], {}, true));
    closeDialog();
}

function closeDialog() {
    showDialog.value = false;
}

function getPageTitle() {
    const contextTitle = document.getElementById('context-title');
    let text = document.getElementById('page-title').textContent.trim();

    // We are inside some context (course, institute, ...), include the context title
    if (contextTitle) {
        // Courses have some <span>s with different parts of the title
        const children = contextTitle.querySelectorAll('.course-type, .course-name, .course-semester');

        if (children.length > 0) {
            text = [...children].map((node) => node.textContent.trim()).join(' ')
                + ' - '
                + text;
        } else {
            text = contextTitle.textContent.trim() + ' - ' + text;
        }
    }

    return text;
}

onMounted(() => {
    const sqids = new Sqids();
    const randomNumbers = Array.from({length: 3}, () => Math.floor(Math.random() * 1000));
    const alias = sqids.encode(randomNumbers);

    newLink.value = {
        attributes: {
            alias: alias,
            path: window.location.href.replace(/^.*?dispatch\.php/, 'dispatch.php'),
            title: getPageTitle()
        }
    };
    document.getElementById('dummy-create-short-url')?.parentNode.remove();
    document.getElementById('responsive-create-shortlink-dummy')?.remove();
});
</script>

<style scoped>
.in-navigation {
    color: var(--white);
    margin-left: 6px;
    margin-right: 6px;

    &:hover {
        color: var(--white);
        text-decoration: underline;
    }
}
</style>
