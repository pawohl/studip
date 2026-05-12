<script setup>
import {reactive} from 'vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';

const CSRF = STUDIP.CSRF_TOKEN;

const props = defineProps({
    config: {
        type: Object,
    }
});

const form = reactive({
    ...props.config
});

const formActionURL = STUDIP.URLHelper.getURL(`dispatch.php/course/forum/configs/save`);
</script>

<template>
    <form
        class="default"
        :action="formActionURL"
        method="post"
    >
        <input type="hidden" :name="CSRF.name" :value="CSRF.value">

        <fieldset>
            <legend class="hide-in-dialog">
                {{ $gettext('Forum verwalten') }}
            </legend>
            <section>
                <label>
                    {{ $gettext('Wer darf das Forum moderieren?') }}
                    <select name="moderator" v-model="form.moderator">
                        <option value="all">
                            {{ $gettext('Alle Teilnehmenden der Veranstaltung') }}
                        </option>
                        <option value="tutor">
                            {{ $gettext('Tutor/-innen und Lehrende') }}
                        </option>
                        <option value="dozent">
                            {{ $gettext('Nur Lehrende') }}
                        </option>
                    </select>
                </label>
            </section>
            <section>
                <label>
                    <input
                        type="checkbox"
                        :aria-label="$gettext('Kategorien ausblenden')"
                        name="is_categories_hidden"
                        v-model="form.isCategoriesHidden"
                        value="1"
                    />
                    <span>
                        {{ $gettext('Kategorien ausblenden') }}
                    </span>
                </label>
            </section>
        </fieldset>
        <footer data-dialog-button>
            <button type="submit" class="button accept">
                {{ $gettext('Übernehmen') }}
            </button>
            <button class="button cancel" type="button" data-dialog-close>
                {{ $gettext('Abbrechen') }}
            </button>
        </footer>
    </form>
</template>

