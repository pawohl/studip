<script setup>
import {$gettext} from "../../../../assets/javascripts/lib/gettext";
import StudipDateTime from "../../StudipDateTime.vue";

defineProps({
    category: {
        type: Object,
        required: true,
    }
});
</script>

<template>
    <dl class="use-utility-classes">
        <dt>{{ $gettext('Titel') }}</dt>
        <dd>{{ category.name }}</dd>

        <dt>{{ $gettext('Beschreibung') }}</dt>
        <dd class="break-word">
            <p>{{ category.description }}</p>
        </dd>

        <dt>{{ $gettext('Anzahl der Diskussionen') }}</dt>
        <dd>{{ category.meta.discussions_count }}</dd>

        <dt>{{ $gettext('Anzahl der Beiträge') }}</dt>
        <dd>{{ category.meta.postings_count }}</dd>

        <dt>{{ $gettext('Anzahl der Teilnehmenden an der Kategorie') }}</dt>
        <dd>{{ category.meta.users_count }}</dd>

        <dt>{{ $gettext('Letzte Aktivität') }}</dt>
        <dd>
            <template v-if="category.meta.recent_activity">
                <StudipDateTime :iso="category.meta.recent_activity" />
            </template>
            <template v-else>
                {{ $gettext('Keine Aktivität') }}
            </template>
        </dd>

        <dt>{{ $gettext('Erstellt am') }}</dt>
        <dd>
            <StudipDateTime :iso="category.mkdate" />
        </dd>
    </dl>
</template>
