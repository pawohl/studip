<template>
    <div
        class="blubber_thread"
        :class="{ dragover: dragging }"
        :id="blubberThreadId"
        @dragover.prevent="dragover"
        @dragleave.prevent="dragleave"
        @drop.prevent="onDrop"
    >
        <ThreadSubscriber
            v-if="threadNotifications"
            class="hidden-medium-up"
            :followed="threadFollowed"
            @subscribe-thread="onSubscribeThread"
        />
        <div v-if="emptyBlubber" class="empty_blubber_background">
            <div>{{ $gettext('Starte die Konversation jetzt!') }}</div>
        </div>
        <div v-else class="scrollable_area" :class="{ scrolled }" ref="scrollable">
            <div class="all_content">
                <ol class="comments" aria-live="polite">
                    <li class="more" v-if="moreCommentsUp">
                        <studip-asset-img file="loading-indicator.svg" width="20"></studip-asset-img>
                    </li>

                    <BlubberComment
                        v-for="comment in sortedComments"
                        :key="comment.id"
                        :comment="comment"
                        :editing="commentEditing && comment.id === commentEditing.id"
                        @answer-comment="onAnswerComment"
                        @change-comment="onChangeComment"
                        @edit-comment="onEditComment"
                        @remove-comment="onRemoveComment"
                    ></BlubberComment>

                    <li class="more" v-if="moreCommentsDown">
                        <studip-asset-img file="loading-indicator.svg" width="20"></studip-asset-img>
                    </li>
                </ol>
            </div>
        </div>

        <BlubberComposer
            ref="composer"
            v-if="threadCommentable"
            v-model="composerText"
            :placeholder="writerTextareaPlaceholder"
            :progress="uploadProgress"
            @add-posting="onAddPosting"
            @edit-previous="onEditPrevious"
            @pick-files="onPickFiles"
        ></BlubberComposer>
    </div>
</template>

<script>
import BlubberComment from './Comment.vue';
import BlubberComposer from './Composer.vue';
import ThreadSubscriber from './ThreadSubscriber.vue';

export default {
    name: 'blubber-thread',
    components: {
        BlubberComment,
        BlubberComposer,
        ThreadSubscriber,
    },
    emits: [
        'add-posting',
        'change-comment',
        'load-newer',
        'load-older',
        'pick-files',
        'remove-comment',
        'subscribe-thread',
    ],
    props: {
        comments: {
            type: Array,
            required: true,
        },
        thread: {
            type: Object,
            required: true,
        },
        moreCommentsDown: {
            type: Boolean,
            default: false,
        },
        moreCommentsUp: {
            type: Boolean,
            default: false,
        },
        uploadProgress: {
            type: Number,
            default: 0,
        },
    },
    data: () => ({
        commentEditing: null,
        composerText: '',
        dragging: false,
        scrolled: false,
        scrollPosition: {},
    }),
    computed: {
        threadCommentable() {
            return this.thread['is-commentable'];
        },
        threadFollowed() {
            return this.thread['is-followed'];
        },
        threadNotifications() {
            return this.thread['may-disable-notifications'];
        },

        blubberThreadId() {
            return 'blubberthread_' + this.thread.id;
        },
        emptyBlubber() {
            return this.comments.length === 0;
        },
        sortedComments() {
            return _.sortBy(this.comments, 'mkdate');
        },
        writerTextareaPlaceholder() {
            return this.$gettext('Nachricht schreiben. Enter zum Abschicken.');
        },
    },
    methods: {
        dragover(event) {
            this.dragging = event.dataTransfer.types.includes('Files');
        },
        dragleave() {
            this.dragging = false;
        },

        scrollDown() {
            this.$nextTick(() => {
                const scrollable = this.$refs.scrollable;
                const scroll = () => {
                    const height = this.$refs.scrollable.querySelector('.all_content').getBoundingClientRect().height;
                    scrollable.scrollTo(0, height);
                };
                scrollable.querySelectorAll('img').forEach((img) => img.addEventListener('load', scroll));
                scroll();
            });
        },

        handleScroll() {
            const el = this.$refs.scrollable;
            const threadPosting = el.querySelector('.all_content');
            const threadPostingHeight = threadPosting?.scrollHeight ?? 0;

            this.scrolled = el.scrollTop > 0;

            if (this.moreCommentsUp && el.scrollTop < 1000) {
                this.$emit('load-older');
            }

            if (this.moreCommentsDown && el.scrollTop > threadPostingHeight - 1000) {
                this.$emit('load-newer');
            }
        },

        onAddPosting(text) {
            this.$emit('add-posting', text);
            clearBlubberMemory(this.thread);
        },
        onAnswerComment(comment) {
            const quoteContent = comment.content.replace(/\[quote[^\]]*\].*\[\/quote\]/g, '').trim();
            const quote = `[quote=${comment.author['formatted-name']}]${quoteContent} [/quote]\n`;
            this.composerText = quote;
        },
        onChangeComment(comment) {
            this.commentEditing = null;
            this.$emit('change-comment', comment);
            this.$refs.composer.focusTextarea();
        },
        onDrop(event) {
            if (!event.dataTransfer?.types.includes('Files')) {
                return;
            }

            this.$emit('pick-files', event.dataTransfer.files);
            this.dragleave();
        },
        onEditComment(comment) {
            this.commentEditing = comment;
        },
        onEditPrevious() {
            this.commentEditing = this.sortedComments[
                this.sortedComments.findLastIndex((comment) => {
                    return comment.isMine();
                })
            ];
        },
        onPickFiles(files) {
            this.$emit('pick-files', files);
        },
        onRemoveComment(comment) {
            this.commentEditing = null;
            this.$emit('remove-comment', comment);
        },
        onSubscribeThread(subscribeThread) {
            this.$emit('subscribe-thread', subscribeThread);
        },
    },
    mounted() {
        this.handleDebouncedScroll = _.debounce(this.handleScroll, 100);

        this.$nextTick(() => {
            this.$refs.composer.scrollIntoView();
        });
    },
    beforeUnmount() {
        if (!this.emptyBlubber) {
            this.$refs.scrollable.removeEventListener('scroll', this.handleDebouncedScroll);
        }
    },
    beforeUpdate() {
        if (!this.emptyBlubber) {
            const { scrollHeight, scrollTop } = this.$refs.scrollable;
            this.scrollPosition = { scrollHeight, scrollTop };
        }
    },
    updated() {
        if (!this.emptyBlubber) {
            // maintain scroll position when loading older comments
            const newScrollTop =
                this.$refs.scrollable.scrollHeight - this.scrollPosition.scrollHeight + this.scrollPosition.scrollTop;
            this.$refs.scrollable.scrollTo(0, newScrollTop);
        }
    },
    watch: {
        emptyBlubber: {
            handler(isEmpty) {
                if (!isEmpty) {
                    // when everything is initialized
                    this.$nextTick(() => {
                        this.$refs.scrollable.addEventListener('scroll', this.handleDebouncedScroll);

                        if (this.comments.length > 0) {
                            this.scrollDown();
                        }

                        const memory = getBlubberMemory(this.thread);
                        if (memory) {
                            this.composerText = memory;
                        }
                    });
                }
            },
            immediate: true,
        },
        composerText(text) {
            setBlubberMemory(this.thread, text);
        }
    }
};

function clearBlubberMemory(thread) {
    if (thread?.id) {
        window.sessionStorage.removeItem(`BlubberMemory-Writer-${thread.id}`);
    }
}

function getBlubberMemory(thread) {
    return thread?.id ? window.sessionStorage.getItem(`BlubberMemory-Writer-${thread.id}`) : null;
}

function setBlubberMemory(thread, memory) {
    if (thread?.id) {
        window.sessionStorage.setItem(`BlubberMemory-Writer-${thread.id}`, memory);
    }
}
</script>
<style lang="scss" scoped>
.empty_blubber_background {
    flex: 1;
}
</style>
