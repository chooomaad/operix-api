<template>
  <div class="min-h-screen bg-gray-50 dark:bg-surface-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">

      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-600 mb-4">
          <span class="text-white font-bold text-xl">O</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('auth.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('auth.subtitle') }}</p>
      </div>

      <!-- Card -->
      <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-gray-200 dark:border-surface-700 p-8">

        <!-- Error -->
        <div v-if="error" class="mb-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-400">
          {{ error }}
        </div>

        <!-- Step 1: Email -->
        <form v-if="step === 'email'" @submit.prevent="sendCode" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ t('auth.email_label') }}
            </label>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              :placeholder="t('auth.email_placeholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition text-sm"
            />
          </div>
          <button
            type="submit"
            :disabled="loading"
            class="w-full py-2.5 px-4 rounded-lg bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white font-semibold text-sm transition"
          >
            {{ loading ? t('auth.sending') : t('auth.send_code') }}
          </button>
        </form>

        <!-- Step 2: OTP code -->
        <form v-else @submit.prevent="verify" class="space-y-5">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-1">
            <Icon name="check" class="w-4 h-4 text-green-500" />
            {{ t('auth.code_sent', { email }) }}
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ t('auth.code_label') }}
            </label>
            <input
              v-model="code"
              type="text"
              inputmode="numeric"
              maxlength="6"
              pattern="\d{6}"
              required
              autofocus
              :placeholder="t('auth.code_placeholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition text-sm tracking-widest text-center text-lg font-mono"
            />
            <p class="mt-1.5 text-xs text-gray-400">{{ t('auth.code_hint') }}</p>
          </div>

          <button
            type="submit"
            :disabled="loading || code.length < 6"
            class="w-full py-2.5 px-4 rounded-lg bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white font-semibold text-sm transition"
          >
            {{ loading ? t('auth.verifying') : t('auth.verify') }}
          </button>

          <button
            type="button"
            @click="step = 'email'; code = ''; error = null"
            class="w-full text-sm text-gray-500 hover:text-brand-600 dark:hover:text-brand-400 transition"
          >
            ← {{ t('auth.change_email') }}
          </button>
        </form>
      </div>

      <!-- Lang + dark toggle at bottom -->
      <div class="mt-6 flex items-center justify-center gap-4">
        <button
          @click="toggleLocale"
          class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition font-medium"
        >
          {{ locale === 'fr' ? 'English' : 'Français' }}
        </button>
        <span class="text-gray-300 dark:text-gray-600">|</span>
        <button @click="theme.toggle()" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
          {{ theme.dark ? '☀ Light' : '☾ Dark' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { setLocale } from '../../i18n'
import Icon from '../../components/ui/Icon.vue'

const { t, locale } = useI18n()
const auth   = useAuthStore()
const theme  = useThemeStore()
const router = useRouter()

const step    = ref('email')
const email   = ref('')
const code    = ref('')
const loading = ref(false)
const error   = ref(null)

function toggleLocale() {
  setLocale(locale.value === 'fr' ? 'en' : 'fr')
}

async function sendCode() {
  loading.value = true
  error.value   = null
  try {
    await auth.requestOtp(email.value)
    step.value = 'code'
  } catch (e) {
    error.value = e.response?.data?.message || t('common.error')
  } finally {
    loading.value = false
  }
}

async function verify() {
  loading.value = true
  error.value   = null
  try {
    await auth.verifyOtp(email.value, code.value)
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || t('common.error')
  } finally {
    loading.value = false
  }
}
</script>
