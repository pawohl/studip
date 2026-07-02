<template>
    <div class="formpart range_input">
        <input type="range"
               :name="name"
               :min="min"
               :max="max"
               :step="step"
               :aria-valuemin="min"
               :aria-valuemax="max"
               :aria-valuenow="value"
               v-model.number="value">
        <output for="fader">
            {{ $gettext('%{myValue} von %{max}', {myValue: value, max: max}) }}
        </output>
    </div>
</template>

<script>
export default {
    name: 'range-input',
    emits: ['update:modelValue'],
    props: {
        name: {
            type: String,
            required: true
        },
        modelValue: {
            required: false,
            default: 1
        },
        min: {
            type: Number,
            required: false,
            default: 1
        },
        max: {
            type: Number,
            required: false,
            default: 10
        },
        step: {
            type: Number,
            required: false,
            default: 1
        }
    },
    inheritAttrs: false,
    computed: {
        value: {
            get() {
                const value = Number(this.modelValue ?? this.min);

                if (value < this.min) {
                    return this.min;
                }

                if (value > this.max) {
                    return this.max;
                }

                return value;
            },
            set(value) {
                this.$emit('update:modelValue', Number(value));
            }
        }
    }
}
</script>
