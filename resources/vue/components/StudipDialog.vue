<template>
    <Teleport to="body">
        <focus-trap v-model="trap">
            <div class="studip-dialog"
                 @keydown.esc="closeDialog"
                 v-bind="$attrs"
                 :style="{ zIndex: zIndex }"
            >
                <transition name="dialog-fade">
                    <div class="studip-dialog-backdrop" v-if="true">
                        <vue-resizeable
                            class="resizable"
                            style="position: absolute"
                            ref="resizableComponent"
                            :dragSelector="dragSelector"
                            :active="handlers"
                            :fit-parent="true"
                            :left="left"
                            :top="top"
                            :width="currentWidth"
                            :height="currentHeight"
                            :min-width="minW"
                            :min-height="minH"
                            @mount="initSize"
                            @resize:move="resizeHandler"
                            @resize:start="resizeHandler"
                            @resize:end="resizeHandler"
                            @drag:move="resizeHandler"
                            @drag:start="resizeHandler"
                            @drag:end="resizeHandler"
                        >
                            <div
                                :style="{ width: dialogWidth, height: dialogHeight, top: top, left: left }"
                                :class="[
                                    { 'studip-dialog-warning': question, 'studip-dialog-alert': alert },
                                    'studip-dialog-body',
                                    $attrs.class,
                                ]"
                                role="dialog"
                                aria-modal="true"
                                :aria-labelledby="dialogTitleId"
                                :aria-describedby="dialogDescId"
                                ref="dialog"
                            >
                                <header class="studip-dialog-header">
                                    <span
                                        :id="dialogTitleId"
                                        class="studip-dialog-title"
                                        :title="dialogTitle"
                                        role="heading"
                                        aria-level="2"
                                    >
                                        {{ dialogTitle }}
                                    </span>
                                    <slot name="dialogHeader"></slot>
                                    <button
                                        :aria-label="$gettext('Diesen Dialog schließen')"
                                        :title="$gettext('Schließen')"
                                        class="studip-dialog-close-button"
                                        @click="closeDialog"
                                    ></button>
                                </header>
                                <section
                                    :id="dialogDescId"
                                    :style="{ height: contentHeight }"
                                    class="studip-dialog-content"
                                >
                                    <slot name="dialogContent"></slot>
                                    <div v-if="message">{{ message }}</div>
                                    <div v-if="question">{{ question }}</div>
                                    <div v-if="alert">{{ alert }}</div>
                                </section>
                                <footer class="studip-dialog-footer" ref="footer">
                                    <div class="studip-dialog-footer-buttonset-left">
                                        <slot name="dialogButtonsBefore"></slot>
                                    </div>
                                    <div class="studip-dialog-footer-buttonset-center">
                                        <button
                                            v-if="buttonA"
                                            :title="buttonA.text"
                                            :class="[buttonA.class]"
                                            :disabled="buttonA.disabled"
                                            class="button"
                                            type="button"
                                            @click="confirmDialog"
                                        >
                                            {{ buttonA.text }}
                                        </button>
                                        <slot name="dialogButtons"></slot>
                                        <button
                                            v-if="buttonB"
                                            :title="buttonB.text"
                                            :class="[buttonB.class]"
                                            class="button"
                                            type="button"
                                            ref="buttonB"
                                            @click="closeDialog"
                                        >
                                            {{ buttonB.text }}
                                        </button>
                                    </div>
                                    <div class="studip-dialog-footer-buttonset-right">
                                        <slot name="dialogButtonsAfter"></slot>
                                    </div>
                                </footer>
                            </div>
                        </vue-resizeable>
                    </div>
                </transition>
            </div>
        </focus-trap>
    </Teleport>
</template>

<script>
import { FocusTrap } from 'focus-trap-vue';
import VueResizeable from 'vue-resizable';
let uuid = 0;
const dialogPadding = 3;

export default {
    name: 'studip-dialog',
    components: {
        FocusTrap,
        VueResizeable,
    },
    emits: ['close', 'confirm'],
    props: {
        height: {
            type: [String, Number],
            default: 300,
        },
        width: {
            type: [String, Number],
            default: 450,
        },
        title: String,
        confirmText: String,
        closeText: String,
        confirmShow: {
            type: Boolean,
            default: true,
        },
        confirmDisabled: {
            type: Boolean,
            default: false,
        },
        confirmClass: String,
        closeClass: [String, Boolean],
        question: String,
        alert: String,
        message: String,
        defaultFocus: {
            type: Boolean,
            default: true,
        },
    },
    data() {
        const dialogId = uuid++;

        return {
            trap: true,
            dialogTitleId: `studip-dialog-title-${dialogId}`,
            dialogDescId: `studip-dialog-desc-${dialogId}`,

            currentWidth: 450,
            currentHeight: 300,
            minW: 100,
            minH: 100,
            left: 0,
            top: 0,
            dragSelector: '.studip-dialog-header',
            handlers: ['r', 'rb', 'b', 'lb', 'l', 'lt', 't', 'rt'],
            fit: false,
            footerHeight: 68,

            zIndex: null,
        };
    },
    computed: {
        buttonA() {
            let button = false;
            if (this.message) {
                return false;
            }
            if (this.question || this.alert) {
                button = {};
                button.text = this.$gettext('Ja');
                button.class = 'accept';
            }
            if (this.confirmText && this.confirmShow) {
                button = {};
                button.text = this.confirmText;
                button.class = this.confirmClass;
                button.disabled = this.confirmDisabled;
            }

            return button;
        },
        buttonB() {
            let button = false;
            if (this.message) {
                button = {};
                button.text = this.$gettext('Ok');
                button.class = '';
            }
            if (this.question || this.alert) {
                button = {};
                button.text = this.$gettext('Nein');
                button.class = 'cancel';
            }
            if (this.closeText) {
                button = {};
                button.text = this.closeText;
                if (typeof this.closeClass === 'string') {
                    button.class = this.closeClass;
                } else if (this.closeClass === false) {
                    // keine Klasse setzen
                } else {
                    button.class = 'cancel';
                }
            }

            return button;
        },
        dialogTitle() {
            if (this.title) {
                return this.title;
            }
            if (this.alert || this.question) {
                return this.$gettext('Bitte bestätigen Sie die Aktion');
            }
            if (this.message) {
                return this.$gettext('Information');
            }
            return '';
        },
        dialogWidth() {
            return this.currentWidth ? this.currentWidth - dialogPadding * 4 + 'px' : 'unset';
        },
        dialogHeight() {
            return this.currentHeight ? this.currentHeight - dialogPadding * 4 + 'px' : 'unset';
        },
        contentHeight() {
            return this.currentHeight ? this.currentHeight - this.footerHeight + 'px' : 'unset';
        },
    },
    methods: {
        closeDialog() {
            this.$emit('close');
        },
        confirmDialog() {
            this.$emit('confirm');
        },
        initSize() {
            this.currentWidth = parseInt(this.width, 10);
            this.currentHeight = parseInt(this.height, 10);
            if (window.innerWidth > this.currentWidth) {
                this.left = (window.innerWidth - this.currentWidth) / 2;
            } else {
                this.left = 5;
                this.currentWidth = window.innerWidth - 16;
            }

            this.top = (window.innerHeight - this.currentHeight) / 2;
            this.footerHeight = this.$refs.footer.offsetHeight;
        },
        resizeHandler(data) {
            this.currentWidth = data.width;
            this.currentHeight = data.height;
            this.left = data.left;
            this.top = data.top;
            this.handleResizeBlur();
        },
        handleResizeBlur() {
            let el = this.$refs.resizableComponent.$el.querySelector(':focus');
            if (el) {
                el.blur();
            }
        },
    },
    mounted() {
        if (this.defaultFocus) {
            this.$nextTick().then(() => {
                this.$refs.buttonB.focus();
            });
        }
    },
    created() {
        const maxZIndex = Array.from(document.querySelectorAll('.studip-dialog')).reduce((acc, el) => {
            const style = getComputedStyle(el);
            return Math.max(acc, Number.parseInt(style.zIndex, 10));
        }, 1000);
        this.zIndex = maxZIndex + 1;
    },
};
</script>
