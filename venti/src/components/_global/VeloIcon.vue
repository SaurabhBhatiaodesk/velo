<template lang="pug">
.velo-icon.inline-block.vertical-middle
  .flex.flex-center(
    v-if="icon && icon.length"
    :style="{ width: size }"
    v-html="icon"
  )
  slot(name="tooltip")
    q-tooltip.velo-tooltip.text-weight-medium(
      v-if="tooltip.length"
    ) {{ tooltip }}
</template>
<script>
import { defineComponent, inject, computed } from 'vue'
import { colors } from 'quasar'
export default defineComponent({
  name: 'VeloIcon',

  props: {
    name: {
      type: String,
      required: false,
      default: 'velo'
    },
    color: {
      type: String,
      required: false,
      default: 'black'
    },
    size: {
      type: String,
      required: false,
      default: '1em'
    },
    tooltip: {
      type: String,
      required: false,
      default: ''
    },
    active: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  setup (props) {
    const IconSrc = inject('iconSrc')
    const { getPaletteColor } = colors
    // const iconRaw = ref('')

    const realColor = computed(() => {
      if (props.color.startsWith('#')) {
        return props.color
      }
      if (props.active) {
        const primaryBlue = getPaletteColor('primaryBlue')
        if (props.color === 'primaryBlue' || props.color === primaryBlue) {
          return getPaletteColor('black')
        } else {
          return primaryBlue
        }
      }
      return getPaletteColor(props.color)
    })

    const icon = computed(() => IconSrc(props.name, realColor.value))
    //
    // onMounted(async () => {
    //   const module = await import(`./../../assets/icons/${props.name}.svg?raw`)
    //   iconRaw.value = module.default.replace(/^\/@fs/, '')
    // })

    return {
      icon,
      realColor
    }
  }
})
</script>
<style lang="sass">
.velo-tooltip
  background-color: $secondaryBlue
</style>
