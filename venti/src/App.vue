<template lang="pug">
router-view
</template>
<script>
import { defineComponent, inject } from 'vue'
import { useQuasar } from 'quasar'
const langList = import.meta.glob('../node_modules/quasar/lang/*.mjs')

export default defineComponent({
  name: 'App',

  setup () {
    const $q = useQuasar()
    const bus = inject('Bus')

    bus.on('languagSelected', langIso => {
      langIso = langIso.split('_').join('-')
      for (const i in langList) {
        if (i.split('lang/')[1].split('.mjs')[0] === langIso) {
          langList[i]().then(lang => {
            bus.emit('languagSelected.return', langIso)
            $q.cookies.set('velo_returns.locale', langIso, { path: '/' })
            $q.lang.set(lang.default)
          })
          break
        }
      }
    })

    return {}
  }
})
</script>
