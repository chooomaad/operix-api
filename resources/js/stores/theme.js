import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useThemeStore = defineStore('theme', () => {
    const dark = ref(localStorage.getItem('operix_dark') === 'true')

    function apply() {
        document.documentElement.classList.toggle('dark', dark.value)
    }

    function toggle() {
        dark.value = !dark.value
        localStorage.setItem('operix_dark', dark.value)
        apply()
    }

    apply()

    return { dark, toggle }
})
