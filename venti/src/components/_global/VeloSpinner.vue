<template lang="pug">
q-spinner-puff(
  :color="color"
  :size="size"
)
</template>
<script>
export default {
  name: 'VeloSpinner',

  props: {
    color: {
      type: String,
      required: false,
      default: 'primaryBlue'
    },
    size: {
      type: String,
      required: false,
      default: '50px'
    }
  }
}
</script>
