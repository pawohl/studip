<script setup>
import {$gettext} from '@/assets/javascripts/lib/gettext';
import StudipSelect from '@/vue/components/StudipSelect.vue';
</script>

<template>
    <StudipSelect
        :placeholder="$gettext('Auswählen')"
        label="name"
        :clearable="true"
        :reduce="tag => {
            if(tag.name) {
                return tag;
            }

            return { name: tag };
        }"
        :closeOnSelect="false"
        v-bind="$attrs"
    >
        <template #selected-option="{name}">
            <span>{{ name }}</span>
        </template>

        <template #no-options>
          <div>{{ $gettext('Es gibt keine Schlagworte.') }}</div>
        </template>
    </StudipSelect>
</template>
