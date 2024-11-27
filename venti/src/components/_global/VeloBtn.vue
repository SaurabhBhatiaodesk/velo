<template lang="pug">
q-btn.rounded-borders.bg-gray.q-px-md(
  :class="'text-' + realColor"
  clickable
  v-ripple
  no-caps
  dense
  flat
  @click="$emit('click')"
)
  slot
  .flex.justify-between.items-center
    velo-spinner(
      v-if="loading"
      size="1em"
      :color="realColor"
    )
    velo-icon(
      v-else
      size="1em"
      :name="icon"
      :color="realColor"
    )
    .q-pl-sm {{ label }}
</template>
<script>
import { defineComponent, computed } from 'vue'

export default defineComponent({
  name: 'VeloBtn',

  props: {
    label: {
      type: String,
      required: true
    },
    icon: {
      type: String,
      required: false,
      default: 'velo'
    },
    color: {
      type: String,
      required: false,
      default: 'black'
    },
    active: {
      type: Boolean,
      required: false,
      default: false
    },
    loading: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'click'
  ],

  setup (props) {
    const realColor = computed(() => {
      if (props.active) {
        if (props.color === 'primaryBlue') {
          return 'black'
        } else {
          return 'primaryBlue'
        }
      }
      return props.color
    })

    return {
      realColor
    }
  }
})
</script>
<style lang="sass" scoped>
</style>
