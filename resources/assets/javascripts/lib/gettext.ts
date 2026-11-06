import {createGettext, LanguageData} from 'vue3-gettext';
import * as defaultTranslations from '../../../../locale/de/LC_MESSAGES/js-resources.json';
import eventBus from './event-bus';

interface StringDict {
    [key: string]: string;
}

interface InstalledLanguage {
    name: string;
    selected: boolean;
}

interface InstalledLanguages {
    [key: string]: InstalledLanguage;
}

type Translation = LanguageData;

type Translations = {
    [language: string]: LanguageData;
};

const DEFAULT_LANG = 'de_DE';
const DEFAULT_LANG_NAME = 'Deutsch';

const state = getInitialState();

const gettext = createGettext({
    availableLanguages: getAvailableLanguages(),
    defaultLanguage: state.locale,
    silent: false,
    translations: {
        [DEFAULT_LANG]: {}
    },
    mutedLanguages: [DEFAULT_LANG],
    setGlobalProperties: true,
    globalProperties: {
        language: ['$language'],
        gettext: ['$gettext'],
        pgettext: ['$pgettext'],
        ngettext: ['$ngettext'],
        npgettext: ['$npgettext'],
        interpolate: ['$gettextInterpolate'],
    },
    provideDirective: true,
    provideComponent: true,
});

setLocale(state.locale);

export default gettext;

async function updateTranslations() {
    const translations: Translations = {};

    for (const key of Object.keys(getAvailableLanguages())) {
        if (state.locale === key) {
            translations[key] = await getTranslations(key);
        }
    }
    gettext.translations = translations;
}

export function getLocale() {
    return state.locale;
}

export async function setLocale(locale = getInitialLocale()) {
    if (!(locale in getInstalledLanguages())) {
        throw new Error('Invalid locale: ' + locale);
    }

    state.locale = locale;
    if (state.translations[state.locale] === null) {
        state.translations[state.locale] = await getTranslations(state.locale);
    }

    await updateTranslations();

    eventBus.emit('studip:set-locale', state.locale);
}

function getAvailableLanguages() {
    return Object.entries(getInstalledLanguages()).reduce((memo, [lang, { name }]) => {
        memo[lang] = name;

        return memo;
    }, {} as StringDict);
}


function getInitialState() {
    const translations: Translations = Object.entries(getInstalledLanguages()).reduce((memo, [lang]) => {
        memo[lang] = lang === DEFAULT_LANG ? defaultTranslations : '';

        return memo;
    }, {} as Translations);

    return {
        locale: getInitialLocale(),
        translations,
    };
}

function getInitialLocale() {
    for (const [lang, { selected }] of Object.entries(getInstalledLanguages())) {
        if (selected) {
            return lang;
        }
    }

    return DEFAULT_LANG;
}

function getInstalledLanguages(): InstalledLanguages {
    return window?.STUDIP?.INSTALLED_LANGUAGES ?? { [DEFAULT_LANG]: { name: DEFAULT_LANG_NAME, selected: true } };
}

async function getTranslations(locale: string): Promise<Translation> {
    try {
        const language = locale.split(/[_-]/)[0];
        return await import(`../../../../locale/${language}/LC_MESSAGES/js-resources.json`);
    } catch (exception) {
        console.error('Could not load locale: "' + locale + '"', exception);

        return {};
    }
}

export const $gettext = gettext.$gettext;
export const $ngettext = gettext.$ngettext;
export const $gettextInterpolate = gettext.interpolate;
export const addTranslation = (locale: string, translation: Translation) => {
    if (!(locale in gettext.translations)) {
        gettext.translations[locale] = {};
    }

    Object.entries(translation).forEach(([key, value]) => {
        gettext.translations[locale][key] = value;
    });
};
export const addTranslations = (translations: Translations) => {
    Object.entries(translations).forEach(([locale, translation]) => {
        addTranslation(locale, translation);
    });
};
