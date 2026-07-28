<script setup>
import {$gettext} from '@/assets/javascripts/lib/gettext';
import StudipDateTime from '@/vue/components/StudipDateTime.vue';

defineProps({
    topic: {
        type: Object,
        required: true,
    }
});

</script>

<template>
    <dl class="use-utility-classes">
        <dt>{{ $gettext('Titel') }}</dt>
        <dd>{{ topic.name }}</dd>

        <dt>{{ $gettext('Beschreibung') }}</dt>
        <dd class="break-word" v-html="topic.description_html">
        </dd>

        <template v-if="topic.category">
            <dt>{{ $gettext('Kategorie') }}</dt>
            <dd>{{ topic.category.name }}</dd>
        </template>

        <dt>{{ $gettext('Anzahl der Diskussionen') }}</dt>
        <dd>{{ topic.meta.discussions_count }}</dd>

        <dt>{{ $gettext('Anzahl der Beiträge') }}</dt>
        <dd>{{ topic.meta.postings_count }}</dd>

        <dt>{{ $gettext('Anzahl der Teilnehmenden am Thema') }}</dt>
        <dd>{{ topic.meta.users_count }}</dd>

        <dt>{{ $gettext('Letzte Aktivität') }}</dt>
        <dd>
            <template v-if="topic.meta.recent_activity">
                <StudipDateTime :iso="topic.meta.recent_activity" />
            </template>
            <template v-else>
                {{ $gettext('Keine Aktivität') }}
            </template>
        </dd>

        <dt>{{ $gettext('Erstellt am') }}</dt>
        <dd>
            <StudipDateTime :iso="topic.mkdate" />
        </dd>
    </dl>
</template>
