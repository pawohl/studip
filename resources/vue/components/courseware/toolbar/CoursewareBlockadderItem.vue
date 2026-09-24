<template>
    <li class="cw-blockadder-item-wrapper">
        <span class="cw-sortable-handle cw-sortable-handle-blockadder"></span>
        <button class="cw-blockadder-item" :class="['cw-blockadder-item-' + type]" @click.prevent="addBlock">
            <header class="cw-blockadder-item-title">
                {{ title }}
            </header>
            <p class="cw-blockadder-item-description">
                {{ description }}
            </p>
        </button>
        <button
            class="cw-blockadder-item-fav"
            :title="favButtonTitle"
            @click="toggleFavItem()"
        >
            <studip-icon :shape="blockTypeIsFav ? 'star' : 'star-empty'" />
        </button>
    </li>
</template>

<script>
import containerMixin from '@/vue/mixins/courseware/container.js';
import { mapActions, mapGetters } from 'vuex';

export default {
    name: 'courseware-blockadder-item',
    mixins: [containerMixin],
    components: {},
    props: {
        title: String,
        description: String,
        type: String,
    },
    data() {
        return {
            showInfo: false,
            addInProgress: false,
        };
    },
    computed: {
        ...mapGetters({
            blockAdder: 'blockAdder',
            blockById: 'courseware-blocks/byId',
            containerById: 'courseware-containers/byId',
            favoriteBlockTypes: 'favoriteBlockTypes',
            lastCreatedBlock: 'courseware-blocks/lastCreated',
        }),
        blockTypeIsFav() {
            return this.favoriteBlockTypes.some((type) => type.type === this.type);
        },
        favButtonTitle() {
            if (this.blockTypeIsFav) {
                return this.$gettext(
                    '%{ blockName } Block aus den Favoriten entfernen',
                    { blockName: this.title }
                );
            }

            return this.$gettext(
                '%{ blockName } Block zu Favoriten hinzufügen',
                { blockName: this.title }
            );
        }
    },
    methods: {
        ...mapActions({
            companionInfo: 'companionInfo',
            companionSuccess: 'companionSuccess',
            companionWarning: 'companionWarning',
            createBlock: 'createBlockInContainer',
            lockObject: 'lockObject',
            unlockObject: 'unlockObject',
            loadBlock: 'courseware-blocks/loadById',
            updateContainer: 'updateContainer',
            removeFavoriteBlockType: 'removeFavoriteBlockType',
            addFavoriteBlockType: 'addFavoriteBlockType',
            setAdderStorage: 'coursewareBlockAdder',
        }),
        async addBlock() {
            if (!this.addInProgress) {
                this.addInProgress = true;
                this.setAdderStorage({
                    container: this.blockAdder.container,
                    section: this.blockAdder.section,
                    type: this.type ,
                    position: false
                });
                await this.addNewBlock();
                this.addInProgress = false;
            }
        },
        toggleFavItem() {
            if (this.blockTypeIsFav) {
                this.removeFavoriteBlockType(this.type);
            } else {
                this.addFavoriteBlockType(this.type);
            }
        },
    },
};
</script>
