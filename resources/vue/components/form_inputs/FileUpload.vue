<template>
    <section>
        <button v-show="!uploading"
               class="button select"
               :class="{studiprequired: required}"
               @click.prevent="openFileSelect">
            <studip-icon shape="upload"></studip-icon>
            <span class="textlabel">
                {{ title }}
            </span>
            <span v-if="required"
                  class="asterisk"
                  :title="$gettext('Dies ist ein Pflichtfeld')"
                  aria-hidden="true">*</span>
        </button>
        <div class="file-count">
            <template v-if="selectedFiles?.length === 0">
                {{ $gettext('Keine Dateien gewählt') }}
            </template>
            <template v-else-if="selectedFiles?.length === 1">
                {{ $gettext('Eine Datei gewählt') }}
            </template>
            <template v-else>
                {{ $gettext('%{number} Dateien gewählt', { number: selectedFiles.length }) }}
            </template>
        </div>
        <input type="file"
               :name="name"
               :id="id"
               :multiple="multiple"
               :accept="accept"
               ref="files"
               class="button"
               :required="required"
               @change="selectFiles">
        <button v-if="selectedFiles.length > 0"
                type="button"
                class="button upload"
                @click.prevent="upload">
            <studip-icon shape="upload"></studip-icon>
            {{ $gettext('Jetzt hochladen') }}
        </button>
        <div v-if="!uploading && uploadedFiles.length > 0">
            <span>
                {{ $gettext('Bereits hochgeladen:') }}
            </span>
            <ul>
                <li v-for="(file, index) in uploadedFiles"
                    :key="index">
                    {{ file.name + ' (' + getTextualFileSize(file.size) + ')' }}
                </li>
            </ul>
        </div>
        <input type="hidden"
               :name="name"
               :value="targetFolder">
        <studip-progress-indicator v-if="uploading"
                                   :size="24">
            {{ $gettext('Wird hochgeladen...') }}
        </studip-progress-indicator>
    </section>
</template>

<script>
import axios from 'axios';
import StudipProgressIndicator from "../StudipProgressIndicator.vue";

export default {
    name: 'FileUpload',
    components: {StudipProgressIndicator},
    props: {
        name: {
            type: String,
            required: true
        },
        title: {
            type: String,
            required: true
        },
        folder: {
            type: String,
            required: true
        },
        uploadUrl: {
            type: String,
            required: true
        },
        id: {
            type: String
        },
        required: {
            type: Boolean,
            default: false
        },
        multiple: {
            type: Boolean,
            default: false
        },
        accept: {
            type: String,
            default: '*/*'
        }
    },
    data() {
        return {
            selectedFiles: [],
            uploading: false,
            uploadedFiles: [],
            targetFolder: ''
        }
    },
    methods: {
        upload() {
            if (this.$refs.files.files.length > 0) {
                this.uploading = true;

                const files = this.$refs.files.files;

                const formData = new FormData();

                let name = this.name;
                if (this.multiple) {
                    name += '[]';
                }

                for (let i = 0; i < files.length; i++) {
                    formData.append(name, files[i]);
                    this.uploadedFiles.push(files[i]);
                }

                axios.post(
                    this.uploadUrl,
                    formData
                ).then(() => {
                    this.uploading = false;
                    this.$refs.files.value = '';
                    this.targetFolder = this.folder;
                }).catch(error => {
                    this.uploadedFiles = [];
                    this.uploading = false;
                    STUDIP.Report.error(this.$gettext('Fehler beim Hochladen'), error);
                });
            }
        },
        getTextualFileSize(bytes) {
            if (bytes < 1024) {
                return this.$gettext('%{size} B', {size: bytes});
            }

            if (bytes < 1024 * 1024) {
                return this.$gettext('%{size} KB', {
                    size: (bytes / 1024).toFixed(2)
                });
            }

            return this.$gettext('%{size} MB', {
                size: (bytes / (1024 * 1024)).toFixed(2)
            });
        },
        openFileSelect() {
            this.$refs.files.click();
        },
        selectFiles() {
            this.selectedFiles = this.$refs.files.files;
        }
    }
}
</script>

<style lang="scss" scoped>
input[type=file] {
    display: none;
}
button {
    margin-top: 0;
    margin-bottom: 0;
    padding: 5px 10px;

    img {
        margin-right: 5px;
        vertical-align: text-bottom;
    }
}
.select {
    margin-right: 0;
}
.file-count {
    border: solid thin var(--light-gray-color-40);
    border-left: unset;
    display: inline-block;
    margin-left: -4px;
    padding: 5px 10px 4px 10px;
    position: relative;
    top: 2px;
}
.upload {
    margin-left: 15px;
}
</style>
