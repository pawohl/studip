<script setup>
import {computed, onMounted, reactive, useTemplateRef} from 'vue';
import StudipSelect from '@/vue/components/StudipSelect.vue';
import {$gettext} from '@/assets/javascripts/lib/gettext';
import {useForumConfig} from '@/vue/store/pinia/forum/ForumConfig';

const CSRF = STUDIP.CSRF_TOKEN;
const forumConfig = useForumConfig();

const props = defineProps({
    topic: {
        type: Object,
        default: () => ({})
    },
    categories: {
        type: Array,
        required: true
    }
});

const topicForm = reactive({
    ...props.topic,
    category: props.categories.find(({ category_id }) => category_id === props.topic.category_id)
});

const formActionURL = computed(() => {
    if (props.topic.topic_id) {
        return STUDIP.URLHelper.getURL(`dispatch.php/course/forum/topics/save/${props.topic.topic_id}`);
    }

    return STUDIP.URLHelper.getURL(`dispatch.php/course/forum/topics/save`);
});

const nameInput = useTemplateRef('name-input');

onMounted(() => {
    nameInput.value.focus();
});
</script>

<template>
    <div class="forum" style="display: flex;">
        <form
            class="default use-utility-classes forum-form"
            :action="formActionURL"
            method="post"
        >
            <input type="hidden" :name="CSRF.name" :value="CSRF.value">
            <fieldset>
                <legend v-if="topic.topic_id" class="hide-in-dialog">
                    {{ $gettext('Thema bearbeiten') }}
                </legend>
                <legend v-else class="hide-in-dialog">
                    {{ $gettext('Neues Thema anlegen') }}
                </legend>

                <section>
                    <label class="studiprequired m-0">
                        <span class="textlabel">{{ $gettext('Name') }}</span>
                        <span :title="$gettext('Name ist ein Pflichtfeld')" aria-hidden="true" class="asterisk">*</span>
                        <input
                            required
                            type="text"
                            name="name"
                            ref="name-input"
                            v-model="topicForm.name"
                            class="max-w-full"
                        />
                    </label>
                </section>

                <section>
                    <label>
                        {{ $gettext('Beschreibung') }}
                        <textarea rows="5" name="description" v-model="topicForm.description"></textarea>
                    </label>
                </section>

                <section v-if="!forumConfig.isCategoriesHidden">
                    <input type="hidden" name="category" :value="JSON.stringify(topicForm.category)">
                    <label for="category-input">
                        {{ $gettext('Kategorie') }}
                    </label>
                    <StudipSelect
                        id="category-input"
                        label="name"
                        :options="categories"
                        v-model="topicForm.category"
                        :reduce="(category) => {
                            if(category.name) {
                                return category;
                            }

                            return { name: category };
                        }"
                        :taggable="true"
                    >
                        <template #selected-option="{name, color}">
                            <div class="flex items-center">
                                <span v-if="color" :style="{ backgroundColor: color, height: '14px', width: '14px', marginRight: '8px'}" aria-hidden="true"></span>
                                <span class="line-clamp-1 flex-1">{{name}}</span>
                            </div>
                        </template>
                        <template #option="{name, color}">
                            <div :style="{ display: 'flex', alignItems: 'center' }">
                                <span v-if="color" :style="{ backgroundColor: color, height: '14px', width: '14px', marginRight: '8px'}" aria-hidden="true"></span>
                                <span :style="{ flex: '1'}" class="line-clamp-1">{{name}}</span>
                            </div>
                        </template>
                        <template #no-options>
                            <div>
                                {{ $gettext('Es gibt keine Kategorie.') }}
                            </div>
                        </template>
                    </StudipSelect>
                </section>
            </fieldset>
            <footer data-dialog-button>
                <button class="button accept">
                    {{ $gettext('Speichern') }}
                </button>
                <button class="button cancel" type="button" data-dialog-close>
                    {{ $gettext('Abbrechen') }}
                </button>
            </footer>
        </form>
    </div>
</template>

