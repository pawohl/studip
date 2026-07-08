<template>
    <div>
        <div hidden>
            <label class="hiddeninput" v-if="required && sortedItems.length === 0">
                <span class="textlabel">{{ label }}</span>
                <input type="text" required value="">
            </label>
        </div>
        <div class="formpart">
            <ul class="clean editablelist">
                <li v-for="item in sortedItems" :key="item.id" :data-type="item.type">
                    <studip-icon v-if="item.icon" :shape="item.icon" role="info" class="text-bottom" alt=""></studip-icon>
                    <input v-if="name" type="hidden" :name="name + '[]'" :value="item.value">
                    <span>{{item.name}}</span>
                    <button v-if="item.deletable" @click.prevent="deleteItem(item)" :title="$gettext('%{ name } löschen', {name: item.name}, true)" class="undecorated">
                        <studip-icon shape="trash" class="text-bottom"></studip-icon>
                    </button>
                </li>
            </ul>
            <label :for="`editablelist-search-${id}`" class="sr-only">
                {{ $gettext('Suchen') }}
            </label>
            <quicksearch v-if="quicksearch"
                         :id="`editablelist-search-${id}`"
                         :searchtype="quicksearch"
                         name="qs"
                         @input="addRange"
                         :placeholder="$gettext('Suchen')"
            />
        </div>

        <label v-if="selectable">
            {{ $gettext('Oder aus Liste auswählen:') }}
            <select @change="quickselect" @keydown="navigate_or_select">
                <option value="">{{ $gettext('Direkt auswählen ...') }}</option>
                <template v-for="(opt, idx) in selectable">
                    <optgroup v-if="opt.label && opt.options" :label="opt.label" :key="idx">
                        <option v-for="(option, index) in opt.options" :disabled="isSelected(option.value)" :value="JSON.stringify({value: option.value, name: option.name})" :key="`group-${index}`">
                            {{ option.name + (isSelected(option.value) ? ' ✓' : '') }}
                        </option>
                    </optgroup>
                    <option v-else :disabled="isSelected(opt.value)" @click="quicksearch" :value="JSON.stringify({value: opt.value, name: opt.name})" :key="`opt-${idx}`">
                        {{ opt.name + (isSelected(opt.value) ? ' ✓' : '') }}
                    </option>
                </template>
            </select>

        </label>
    </div>
</template>

<script>
let uniqueId = 0;

export default {
    name: 'editable-list',
    emits: ['input', 'items'],
    props: {
        name: {
            type: String,
            required: false
        },
        items: {
            required: false,
            type: Array
        },
        quicksearch: {
            required: false
        },
        selectable: {
            type: Array,
            required: false
        },
        category_order: {
            type: Array,
            required: false,
            default: () => [],
        },
        required: {
            required: false,
            type: Boolean,
            default: false
        },
        label: {
            type: String,
            required: false
        }
    },
    data () {
        return {
            resort: false, //this is just for triggering the computed property sortedItems to be sorted again
            preventChangeOfQuickselect: false,
            allItems: this.items ?? [],
            id: uniqueId++,
        };
    },
    methods: {
        addRange (id, name) {
            let icon = null;
            if (id.includes('__')) {
                icon = id.split('__')[1];
                id = id.split('__')[0];
            }
            if (!this.allItems.find(item => item.value === id)) {
                this.allItems.push({
                    value: id,
                    name: name,
                    icon: icon,
                    deletable: true
                });
                this.changed();
            }
        },
        changed () {
            this.resort = !this.resort;
            this.$emit('input', this.allItems.map(function (item) {
                return item.value;
            }));
            this.$emit('items', this.allItems.map(function (item) {
                return {
                    value: item.value,
                    name: item.name,
                    icon: item.icon,
                    deletable: item.deletable
                };
            }));
        },
        quickselect (event) {
            if (event.target.value && !this.preventChangeOfQuickselect) {
                let new_value = JSON.parse(event.target.value);
                this.addRange(new_value.value, new_value.name);
                event.target.value = '';
            }
        },
        navigate_or_select (event) {
            if (['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                //don't trigger change for 250 ms
                this.preventChangeOfQuickselect = true;
                window.setTimeout(() => {
                    this.preventChangeOfQuickselect = false;
                }, 250);
            } else if (event.key === 'Enter') {
                //select current selection
                let new_value = JSON.parse(event.target.value);
                this.addRange(new_value.value, new_value.name);
                event.target.value = '';
            }
        },
        deleteItem (item) {
            this.allItems = this.allItems.filter(i => i.value !== item.value);
            this.changed();
        },
        isSelected (id) {
            if (id.includes('__')) {
                id = id.split('__')[0];
            }
            for (let i in this.allItems) {
                if (this.allItems[i].value === id) {
                    return true;
                }
            }
            return false;
        }
    },
    computed: {
        sortedItems () {
            return [...this.allItems].sort((a, b) => {
                if (a.icon === b.icon) {
                    return a.name.localeCompare(b.name);
                } else {
                    let a_icon = typeof a.icon === 'string' ? a.icon : '';
                    let b_icon = typeof b.icon === 'string' ? b.icon : '';
                    if (this.category_order.indexOf(a_icon) > -1 && this.category_order.indexOf(b_icon) > -1) {
                        return this.category_order.indexOf(a_icon) < this.category_order.indexOf(b_icon) ? -1 : 1;
                    } else {
                        return a_icon.localeCompare(b_icon);
                    }
                }
            });
        }
    },
    mounted () {
        this.$emit('input', this.allItems.map(function (item) {
            return item.value;
        }));
    }
}
</script>
