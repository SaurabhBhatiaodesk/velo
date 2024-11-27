<template lang="pug">
q-dialog(
  maximized
  :model-value="show"
)
  .fit.flex.flex-center.row.q-pa-xl(@click.self="$emit('closed')")
    q-btn.absolute-top-left.rounded-borders.q-ma-md(
      flat
      @click="$emit('closed')"
    )
      velo-icon(
        size="24px"
        color="white"
        name="close"
      )
    q-card.full-height.flex.flex-center.col-12.col-md-11.col-lg-10.q-pa-lg.rounded-borders
      q-scroll-area.fit(
        :visible="true"
      )
        .absolute.fit
          slot
</template>
<script>
import { useQuasar } from 'quasar'
import { defineComponent, computed } from 'vue'

export default defineComponent({
  name: 'PopupDialog',

  props: {
    show: {
      required: true,
      type: Boolean,
      default: false
    },
    closeBtn: {
      required: false,
      type: Boolean,
      default: false
    },
    colClass: {
      required: false,
      type: String,
      default: 'col-8'
    }
  },

  emits: [
    'closed'
  ],

  setup () {
    const { screen } = useQuasar()
    const scrollareaContentStyleObject = computed(() => {
      if (screen.gt.md) {
        return {
          width: '100%',
          height: '100%',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        }
      }
      return {}
    })

    return {
      scrollareaContentStyleObject
    }
  }
})
</script>
<style lang="sass" scoped>
</style>
