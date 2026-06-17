<template>
    <studip-dialog id="studip-user-filter"
                   :title="$gettext('Filter hinzufügen')"
                   height="600"
                   width="900"
                   :confirmText="$gettext('Übernehmen')"
                   confirmClass="button accept"
                   @confirm="submit"
                   :closeText="$gettext('Abbrechen')"
                   closeClass="button cancel"
                   @close="close">
        <template v-slot:dialogContent>
            <section v-for="(element, index) in currentFilter"
                     :key="index">
                <p v-if="index >= 1">
                    {{ $gettext('und') }}
                </p>
                <select v-if="availableFields.length > 0"
                        v-model="element.attributes.type"
                        @change="addFieldConfig(element.attributes.type, index)"
                        :aria-label="$gettext('Feldname')">
                    <option v-for="(field, fIndex) in availableFields"
                            :key="fIndex"
                            :value="field.attributes.typeparam !== null
                                ? field.attributes.type + '_' + field.attributes.typeparam
                                : field.attributes.type">
                        {{ field.attributes.name }}
                    </option>
                </select>
                <select v-if="hasMultipleCompareOps"
                        v-model="element.attributes['compare-operator']"
                        :aria-label="$gettext('Vergleichsoperator')">
                    <option v-for="(name, op) in fieldConfig[element.attributes.type]?.compareOps"
                            :key="op"
                            :value="op">
                        {{ name }}
                    </option>
                </select>
                <select v-if="hasMultipleValues"
                        v-model="element.attributes.value"
                        :aria-label="$gettext('Wert')">
                    <option v-for="(name, value) in fieldConfig[element.attributes.type]?.values"
                            :key="value"
                            :value="value">
                        {{ name }}
                    </option>
                </select>
                <a v-if="element.attributes.type && currentFilter.length > 1"
                   class="undecorated"
                   @click.prevent="removeField(index)"
                   :title="$gettext('Diese Bedingung löschen')"
                   tabindex="0"
                >
                    <studip-icon shape="trash"></studip-icon>
                </a>
            </section>
            <section>
                <button class="button add"
                        @click.prevent="addField">
                    {{ $gettext('Bedingung hinzufügen') }}
                </button>
            </section>
        </template>
    </studip-dialog>
</template>

<script>
export default {
    name: 'StudipUserFilter',
    emits: ['close', 'submit'],
    props: {
        filter: {
            type: Array,
            default: () => []
        },
        context: {
            type: String,
            default: ''
        },
        target: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            availableFields: [],
            currentFilter: this.filter,
            fieldConfig: {}
        }
    },
    methods: {
        addFieldConfig(type, fieldIndex) {
            if (type !== '') {
                if (!this.fieldConfig[type]) {
                    for (let i = 0; i < this.availableFields.length; i++) {
                        let compareType = this.availableFields[i].attributes.type;

                        if (this.availableFields[i].attributes['typeparam'] !== null) {
                            compareType += '_' + this.availableFields[i].attributes['typeparam'];
                        }

                        if (compareType === type) {
                            this.fieldConfig[type] = {
                                type: this.availableFields[i].attributes.type,
                                typeparam: this.availableFields[i].attributes['typeparam'],
                                compareOps: this.availableFields[i].attributes['valid-compare-operators'],
                                values: this.availableFields[i].attributes['valid-values']
                            };
                        }
                    }
                }

                this.currentFilter[fieldIndex].attributes.type = type;
                this.currentFilter[fieldIndex].attributes.realtype = this.fieldConfig[type].type;
                this.currentFilter[fieldIndex].attributes.typeparam = this.fieldConfig[type].typeparam;
                if (!this.currentFilter[fieldIndex].attributes.id) {
                    this.currentFilter[fieldIndex].attributes['compare-operator'] = Object.keys(this.fieldConfig[type].compareOps)[0];
                    this.currentFilter[fieldIndex].attributes.value = Object.keys(this.fieldConfig[type].values)[0];
                }
            }
        },
        addField() {
            this.currentFilter.push({attributes: { type: null, typeparam: null, 'compare-operator': '', value: '' }});
            this.addFieldConfig(this.availableFields[0].attributes.type, this.currentFilter.length - 1);
        },
        removeField(index) {
            this.currentFilter.splice(index, 1);
        },
        submit() {
            // We need to build a new structure here as the "type" attribute looks different for datafield conditions.
            const data = this.currentFilter.map(item => ({
                attributes: {
                    type: item.attributes.realtype,
                    typeparam: item.attributes.typeparam,
                    'compare-operator': item.attributes['compare-operator'],
                    value: item.attributes.value
                }
            }));

            this.$emit('submit', data)
        },
        close() {
            this.$emit('close');
        },
        hasMultipleCompareOps(element) {
            return element.attributes.type
                && element.attributes.type !== ''
                && Object.keys(this.fieldConfig[element.attributes.type]?.compareOps).length > 1;
        },
        hasMultipleValues(element) {
            return element.attributes.type
                && element.attributes.type !== ''
                && Object.keys(this.fieldConfig[element.attributes.type]?.values).length > 1;
        }
    },
    created() {
        STUDIP.jsonapi.withPromises().get(
            'user-filter-fields',
            {
                data: {
                    filter: {
                        context: this.context,
                        target: this.target
                    }
                }
            }
        ).then(response => {
            this.availableFields = response.data;
            if (this.currentFilter?.length === 0) {
                this.addField();
            } else {
                for (let i = 0 ; i < this.currentFilter.length ; i++) {
                    this.addFieldConfig(this.currentFilter[i].attributes.type, i);
                }
            }
        });
    }
}
</script>
