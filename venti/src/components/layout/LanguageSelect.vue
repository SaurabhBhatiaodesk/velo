<template lang="pug">
q-select.language-select(
  filled
  v-model="locale"
  :options="localeOptions"
  label="Language"
  borderless
  emit-value
  map-options
  options-dense
  @update:model-value="onLangSelect"
)
</template>
<script>
import { defineComponent, computed, ref, inject } from 'vue'
import { useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import languages from 'quasar/lang/index.json'

export default defineComponent({
  name: 'LanguageSelect',

  setup () {
    const $q = useQuasar()
    const bus = inject('Bus')
    const { messages, locale } = useI18n({ useScope: 'global' })
    const localeOptions = computed(() => {
      const result = []
      for (const i in languages) {
        if (messages.value[languages[i].isoName]) {
          result.push({
            value: languages[i].isoName,
            label: languages[i].nativeName
          })
        }
      }
      return result
    })

    const qLocale = ref($q.lang.isoName)
    locale.value = $q.lang.isoName

    function onLangSelect (langIso) {
      bus.emit('languagSelected', langIso)
    }

    bus.on('languagSelected.return', langIso => {
      const tawkPosition = {
        position: (document.dir === 'rtl') ? 'bl' : 'br'
      }

      if (window && window.Tawk_API) {
        window.Tawk_API.customStyle = {
          visibility: {
            desktop: tawkPosition,
            mobile: tawkPosition,
            bubble: tawkPosition
          }
        }
      }
      qLocale.value = langIso
    })

    return {
      locale,
      localeOptions,
      onLangSelect
    }
  }
})
</script>
<style lang="sass" scoped>
.language-select
  min-width: 150px
</style>
