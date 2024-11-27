<template lang="pug">
q-dialog(
  maximized
  :model-value="show"
  transition-show="slide-left"
  transition-hide="slide-right"
  @hide="$emit('closed')"
)
  .row.fit.overflow-hidden
    .col-1.col-sm-2.col-md-3.col-lg-5.col-xl-6(@click="$emit('closed')")
    q-card.col-11.col-sm-10.col-md-9.col-lg-7.col-xl-6.column.justify-between
      slot
</template>
<script>
export default {
  name: 'DrawerDialog',

  props: {
    show: {
      type: Boolean,
      required: true
    }
  },

  emits: [
    'closed'
  ]
}
</script>

<style lang="css" scoped>
</style>
