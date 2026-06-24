<template>
    <div class="formpart">
        <p>{{ $gettext('An dieser Stelle prüfen wir automatisch, ob Sie ein Mensch sind.') }}</p>
        <altcha-widget :challenge="challenge"
                       ref="widget"
                       :type="type"
        ></altcha-widget>
    </div>
</template>
<script>
import 'altcha';
import 'altcha/i18n/de';
import 'altcha/i18n/en';

export default {
    name: 'CaptchaInput',
    emits: ['update:model-value'],
    props: {
        name: {
            type: String,
            default: 'altcha'
        },
        challenge: {
            type: String,
            requird: true,
        },
        type: {
            type: String,
            default: 'checkbox',
            validator: (value) => ['checkbox', 'switch', 'native'].includes(value),
        },
        auto: {
            type: String,
            default: null,
            validator: (value) => ['onfocus', 'onload', 'onsubmit'].includes(value),
        }
    },
    mounted() {
        this.$nextTick(() => {
            this.$refs.widget.configure({
                auto: this.auto,
                name: this.name,
                hidefooter: true,
                hidelogo: false,
            });

            this.$refs.widget.addEventListener('statechange', (ev) => {
                if (ev.detail.state === 'verified') {
                    this.$emit('update:model-value', ev.detail.payload);
                }
            })
        });
    }
}
</script>
<style lang="scss">
.altcha-main {
    --altcha-border-width: 0;
    --altcha-border-radius: 0;
    --altcha-color-base: transparent;
    --altcha-color-border: #a0a0a0;
    --altcha-color-text: currentColor;
    --altcha-color-border-focus: currentColor;
    --altcha-color-error-text: var(--red);
    --altcha-color-footer-bg: none;
    --altcha-max-width: auto;

    padding: 0 !important;

    .altcha-checkbox-wrap > label {
        margin-bottom: 0 !important;
    }
}
</style>
