<template lang="pug">
q-layout.bg-white(view="hHh lpR fFf")
  q-page-container
    q-page.fit.flex.flex-center.relative-position
      q-card.velo-returns-card.velo-returns-shadow.rounded-borders.q-px-xl.q-py-lg
        .text-center.text-weight-bolder.q-pb-lg.q-pt-sm
          img.main-logo(
            src="~assets/logo.svg"
            :alt="$t('appTitle')"
          )
          .q-pt-md {{ $t('appTagline') }}
        router-view
        .text-center.text-caption.q-pt-md(v-html="$t('powered')")
      language-select.absolute-bottom-right.q-pa-md
</template>
<script>
import { defineComponent } from 'vue'
import LanguageSelect from 'src/components/layout/LanguageSelect.vue'

export default defineComponent({
  name: 'LayoutsApp',

  components: {
    LanguageSelect
  }
})
</script>
<style lang="sass" scoped>
.main-logo
  width: 130px
.velo-returns-card
  width: 95%
  max-width: 600px
</style>
