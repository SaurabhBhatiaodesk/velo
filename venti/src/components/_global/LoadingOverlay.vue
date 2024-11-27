<template lang="pug">
.velo-loading-overlay
  .text-center
    .text-h6.q-mb-sm(v-if="title.length") {{ title }}
    velo-spinner
</template>
<script>
export default {
  name: 'LoadingOverlay',

  props: {
    title: {
      type: String,
      required: false,
      default: ''
    }
  }
}
</script>
<style lang="sass" scoped>
.velo-loading-overlay
  display: flex
  justify-content: center
  align-items: center
  position: absolute
  top: 0
  bottom: 0
  left: 0
  right: 0
  width: 100%
  height: 100%
  z-index: 10
  background: rgba(white, 0.8)
</style>
