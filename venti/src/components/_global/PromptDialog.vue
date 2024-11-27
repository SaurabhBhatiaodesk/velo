<template lang="pug">
q-dialog(
  maximized
  :model-value="show"
  @hide="$emit('closed')"
)
  .fit.flex.flex-center.row
    q-card.rounded-borders.q-pa-lg
      .text-center.text-h6.text-weight-bold.q-mb-md
        slot(name="title")
      slot
      .flex.justify-end.items-center.q-pt-md
        slot(name="actions")
</template>
<script>
export default {
  name: 'PromptDialog',

  props: {
    show: {
      required: true,
      type: Boolean,
      default: false
    }
  },

  emits: [
    'closed'
  ]
}
</script>
<style lang="sass" scoped>
</style>
