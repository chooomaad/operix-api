import { createI18n } from 'vue-i18n'
import fr from './locales/fr.json'
import en from './locales/en.json'

const savedLocale = localStorage.getItem('operix_locale') || 'fr'

export const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'fr',
    messages: { fr, en },
})

export function setLocale(locale) {
    i18n.global.locale.value = locale
    localStorage.setItem('operix_locale', locale)
    document.documentElement.setAttribute('lang', locale)
}
