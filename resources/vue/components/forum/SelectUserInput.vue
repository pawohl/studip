<script setup>
import {$gettext} from '@/assets/javascripts/lib/gettext';
import StudipSelect from '@/vue/components/StudipSelect.vue';
</script>

<template>
    <StudipSelect
        label="name"
        :placeholder="$gettext('Auswählen')"
        v-bind="$attrs"
    >
        <template #selected-option="{name, avatar_url}">
            <div class="flex items-center">
                <img :src="avatar_url" :alt="name" :style="{ height: '14px', width: '14px', marginRight: '8px'}" />
                <span class="line-clamp-1 flex-1">{{ name }}</span>
            </div>
        </template>

        <template #option="{name, avatar_url}">
            <div :style="{ display: 'flex', alignItems: 'center' }">
                <img :src="avatar_url" :alt="name" :style="{ height: '14px', width: '14px', marginRight: '8px'}" />
                <span :style="{ flex: '1'}" class="line-clamp-1 flex-1">{{ name }}</span>
            </div>
        </template>
        <template #no-options>
            <div>
                {{ $gettext('Es gibt keine Benutzer/-innen.') }}
            </div>
        </template>
    </StudipSelect>
</template>
