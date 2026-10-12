<template>
    <form class="default">
        <section>
            <label>
                {{ $gettext('Nachricht bei fehlgeschlagener Anmeldung') }}
                <textarea name="message" rows="4" cols="50" v-model="messageText"></textarea>
            </label>
        </section>
        <section>
            <studip-message-box v-if="passwordSet" type="warning">
                {{ $gettext('Es ist bereits ein Passwort eingerichtet. Um es zu überschreiben, geben Sie hier ein neues ein.') }}
            </studip-message-box>
            <label>
                {{ $gettext('Zugangspasswort') }}
                <input type="password"
                       v-model="password1"
                       v-allow-plaintext-toggle
                >
            </label>
            <label>
                {{ $gettext('Passwort wiederholen') }}
                <input type="password"
                       v-model="password2"
                       v-allow-plaintext-toggle
                >
            </label>
        </section>
    </form>
</template>

<script>
import { AdmissionRuleMixin } from '../../mixins/AdmissionRuleMixin';

export default {
    name: 'PasswordAdmission',
    mixins: [AdmissionRuleMixin],
    props: {
        hasPassword: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            messageText: this.message || this.$gettext('Für die Anmeldung ist ein Passwort erforderlich.'),
            password1: '',
            password2: '',
            passwordSet: this.hasPassword
        }
    },
    methods: {
        setRuleData(data) {
            this.messageText = data.attributes.payload['message'];
            if (data.attributes.payload.password !== '') {
                this.passwordSet = true;
            }
        },
        validate() {
            this.invalidData = [];
            if (this.password1 === '') {
                this.invalidData.push(this.$gettext('Das Passwort darf nicht leer sein.'));
            }
            if (this.password1 !== this.password2) {
                this.invalidData.push(this.$gettext('Die Passwörter stimmen nicht überein.'));
            }

            return this.invalidData.length === 0;
        }
    },
    computed: {
        payload() {
            return {
                type: 'PasswordAdmission',
                payload: {
                    newPassword: this.password1,
                    message: this.messageText
                }
            }
        }
    }
}
</script>
